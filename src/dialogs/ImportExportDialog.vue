<template>
  <NcModal size="large" name="Import & Export" @close="emit('close')">
    <div class="import-export-modal">
      <div class="ie-header">
        <h3>Import & Export Sponsors / Clients</h3>
        <div class="ie-tabs">
          <button
            type="button"
            class="ie-tab-btn"
            :class="{ active: mode === 'export' }"
            @click="mode = 'export'"
          >
            Export Data
          </button>
          <button
            type="button"
            class="ie-tab-btn"
            :class="{ active: mode === 'import' }"
            @click="mode = 'import'"
          >
            Import Data (CSV / JSON)
          </button>
          <button
            type="button"
            class="ie-tab-btn"
            :class="{ active: mode === 'legacy' }"
            @click="openLegacyReaderTab"
          >
            Legacy reader
          </button>
        </div>
      </div>

      <!-- EXPORT MODE -->
      <div v-if="mode === 'export'" class="ie-section">
        <p class="ie-desc">
          Export your CRM database to Excel-compatible CSV (UTF-8 BOM) or JSON format, including all multiple emails/phones, categories, tiers, assignees, notes, and custom columns.
        </p>

        <div class="export-options">
          <label class="radio-row">
            <input v-model="exportScope" type="radio" value="all">
            <span>All clients ({{ allClients.length }} records)</span>
          </label>
          <label class="radio-row">
            <input v-model="exportScope" type="radio" value="filtered">
            <span>Currently filtered view ({{ filteredClients.length }} records)</span>
          </label>
        </div>

        <div class="export-buttons">
          <NcButton type="primary" @click="exportCsv(false)">
            Export CSV (Excel / Sheets)
          </NcButton>
          <NcButton type="secondary" @click="exportJson">
            Export JSON
          </NcButton>
          <NcButton type="tertiary" @click="exportCsv(true)">
            Download Empty CSV Template
          </NcButton>
        </div>
      </div>

      <!-- IMPORT MODE -->
      <div v-else-if="mode === 'import'" class="ie-section">
        <p class="ie-desc">
          Upload a <code>.csv</code> / <code>.json</code> file or paste CSV text directly below. Existing records with matching UUID, Client ID, or Name will be updated (and any new notes added); new ones will be created.
        </p>

        <div class="file-drop-zone">
          <input
            type="file"
            accept=".csv,.json"
            class="file-input"
            @change="handleFileSelect"
          >
        </div>

        <div class="paste-csv-block">
          <label class="paste-label">Or paste CSV / JSON content directly:</label>
          <textarea
            v-model="pastedText"
            class="csv-textarea"
            rows="5"
            placeholder="Paste CSV or JSON output here (e.g. copied from Legacy reader)..."
            @input="handlePasteInput"
          />
        </div>

        <div v-if="parseError" class="ie-error">
          {{ parseError }}
        </div>

        <div v-if="parsedRecords.length > 0" class="import-preview">
          <div class="preview-summary">
            Ready to import <strong>{{ parsedRecords.length }}</strong> client(s)
            (with <strong>{{ totalParsedNotes }}</strong> note(s)).
          </div>
          <div class="preview-table-wrap">
            <table class="preview-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Client ID</th>
                  <th>Assignee</th>
                  <th>Status</th>
                  <th>Emails</th>
                  <th>Phones</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(rec, idx) in parsedRecords.slice(0, 8)" :key="idx">
                  <td>{{ rec.name }}</td>
                  <td>{{ rec.client_number }}</td>
                  <td>{{ rec.assigned_to }}</td>
                  <td>{{ rec.status }}</td>
                  <td>{{ (rec.emails || []).map(e => e.email).join('; ') }}</td>
                  <td>{{ (rec.phones || []).map(p => p.phone).join('; ') }}</td>
                  <td>{{ (rec.notes || []).length }}</td>
                </tr>
              </tbody>
            </table>
            <div v-if="parsedRecords.length > 8" class="preview-more">
              ...and {{ parsedRecords.length - 8 }} more row(s)
            </div>
          </div>

          <div class="import-actions">
            <NcButton type="primary" :disabled="importing" @click="runImport">
              {{ importing ? 'Importing...' : `Import ${parsedRecords.length} Record(s)` }}
            </NcButton>
          </div>
        </div>

        <div v-if="importResult" class="import-result-banner">
          Created: <strong>{{ importResult.created }}</strong> |
          Updated: <strong>{{ importResult.updated }}</strong>
          <div v-if="importResult.errors && importResult.errors.length" class="ie-error">
            Errors: {{ importResult.errors.join(' | ') }}
          </div>
        </div>
      </div>

      <!-- LEGACY READER MODE -->
      <div v-else-if="mode === 'legacy'" class="ie-section">
        <p class="ie-desc">
          Reads clients, addresses, and notes from the original <code>ticky_clients</code>, <code>ticky_client_addresses</code>, and <code>ticky_client_notes</code> tables and formats them in the exact <strong>New Sticky CRM CSV format</strong> (including the <code>Notes</code> column).
        </p>

        <div class="legacy-controls">
          <NcButton type="secondary" :disabled="legacyLoading" @click="loadLegacyOutput">
            {{ legacyLoading ? 'Reading database...' : 'Refresh Database Output' }}
          </NcButton>
          <NcButton type="primary" :disabled="!legacyCsvOutput" @click="copyLegacyCsv">
            {{ copyFeedback || 'Copy CSV to Clipboard' }}
          </NcButton>
          <NcButton type="secondary" :disabled="!legacyCsvOutput" @click="downloadLegacyCsv">
            Download legacy_ticky.csv
          </NcButton>
          <NcButton type="secondary" :disabled="!legacyCsvOutput" @click="sendLegacyToImportTab">
            Load into Import Tab →
          </NcButton>
          <span class="legacy-count-badge">
            {{ legacyRows.length }} client(s), {{ totalLegacyNotes }} note(s)
          </span>
        </div>

        <textarea
          :value="legacyCsvOutput"
          readonly
          class="csv-textarea legacy-output-area"
          rows="12"
          placeholder="No rows found in ticky_clients table..."
        />
      </div>

      <div class="ie-footer">
        <NcButton type="tertiary" @click="emit('close')">
          Close
        </NcButton>
      </div>
    </div>
  </NcModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { NcModal, NcButton } from '@nextcloud/vue'
import { importClients, fetchLegacyTickyClients, DEFAULT_CRM_CONFIG } from '../services/clientService'

const props = defineProps({
	allClients: { type: Array, default: () => [] },
	filteredClients: { type: Array, default: () => [] },
	crmConfig: { type: Object, default: () => DEFAULT_CRM_CONFIG },
})

const emit = defineEmits(['close', 'imported'])

const mode = ref('export')
const exportScope = ref('all')
const parsedRecords = ref([])
const pastedText = ref('')
const parseError = ref('')
const importing = ref(false)
const importResult = ref(null)

// Legacy reader state
const legacyLoading = ref(false)
const legacyRows = ref([])
const copyFeedback = ref('')

const totalParsedNotes = computed(() => {
	return parsedRecords.value.reduce((acc, r) => acc + ((r.notes && r.notes.length) || 0), 0)
})

const totalLegacyNotes = computed(() => {
	return legacyRows.value.reduce((acc, r) => acc + ((r.notes && r.notes.length) || 0), 0)
})

const getTargetClients = () => {
	return exportScope.value === 'filtered' ? props.filteredClients : props.allClients
}

const escapeCsvCell = (val) => {
	const str = val === null || val === undefined ? '' : String(val)
	if (str.includes('"') || str.includes(',') || str.includes(';') || str.includes('\n') || str.includes('\r')) {
		return '"' + str.replace(/"/g, '""') + '"'
	}
	return str
}

const extractAssigneeFromClientNum = (cn) => {
	if (!cn) return ''
	const trimmed = String(cn).trim()
	const m = trimmed.match(/^([A-Za-zÀ-ž]{2,})[-_]\d+/u)
	if (m && !['KD', 'SP', 'CL', 'CLI', 'CUST', 'PART'].includes(m[1].toUpperCase())) {
		return m[1]
	}
	if (/^[A-Za-zÀ-ž]+(?:\s+[A-Za-zÀ-ž]+)*$/u.test(trimmed)) {
		if (!['KD', 'SP', 'CL', 'CLI', 'CUST', 'PART'].includes(trimmed.toUpperCase())) {
			return trimmed
		}
	}
	return ''
}

const serializeNotesForCsv = (notes) => {
	if (!Array.isArray(notes) || notes.length === 0) return ''
	return notes
		.filter(n => n && (n.content || n.title))
		.map(n => {
			const date = (n.created_at || '').replace(/\|/g, ' ')
			const user = (n.user_id || 'admin').replace(/\|/g, ' ')
			const type = (n.type || 'note').replace(/\|/g, ' ')
			const title = (n.title || '').replace(/\|/g, ' ').replace(/\]/g, ')')
			const body = String(n.content || '').replace(/\r?\n/g, '\\n')
			return `[${date}|${user}|${type}|${title}] ${body}`
		})
		.join(' || ')
}

const parseNotesFromCsvCell = (raw) => {
	if (!raw || !String(raw).trim()) return []
	const str = String(raw).trim()

	// Support JSON array of notes if present
	if (str.startsWith('[{')) {
		try {
			const parsed = JSON.parse(str)
			if (Array.isArray(parsed)) return parsed
		} catch {
		}
	}

	const parts = str.split(/\s*\|\|\s*/).filter(Boolean)
	const notes = []
	for (const part of parts) {
		const m = part.match(/^\[([^|]*)\|([^|]*)\|([^|]*)\|([^\]]*)\]\s*(.*)$/s)
		if (m) {
			notes.push({
				created_at: m[1].trim() || undefined,
				user_id: m[2].trim() || 'admin',
				type: m[3].trim() || 'note',
				title: m[4].trim() || '',
				content: m[5].replace(/\\n/g, '\n').trim(),
			})
		} else {
			notes.push({
				user_id: 'admin',
				type: 'note',
				title: '',
				content: part.replace(/\\n/g, '\n').trim(),
			})
		}
	}
	return notes.filter(n => n.content)
}

/**
 * Formats legacy ticky_clients rows into the exact New Sticky CRM importable CSV format
 */
const legacyCsvOutput = computed(() => {
	if (!legacyRows.value || legacyRows.value.length === 0) {
		return ''
	}
	const headers = [
		'Name',
		'Client ID',
		'Assignee',
		'Status',
		'Tier',
		'Categories',
		'Emails',
		'Phones',
		'Website',
		'VAT ID',
		'Street',
		'City',
		'Postal Code',
		'Country',
		'UUID',
		'Notes',
	]
	const lines = [headers.map(escapeCsvCell).join(',')]

	for (const r of legacyRows.value) {
		const addr = (Array.isArray(r.addresses) && r.addresses[0]) || {}
		const assignee = r.assigned_to || extractAssigneeFromClientNum(r.client_number)

		// Combine contact_email and invoice_email into New Sticky CRM Emails format
		const emailParts = []
		if (r.contact_email && String(r.contact_email).trim()) {
			emailParts.push(String(r.contact_email).trim())
		}
		if (r.invoice_email && String(r.invoice_email).trim() && r.invoice_email !== r.contact_email) {
			emailParts.push(`Billing:${String(r.invoice_email).trim()}`)
		}
		const emailsStr = emailParts.join('; ')

		const phonesStr = r.phone ? String(r.phone).trim() : ''
		const notesStr = serializeNotesForCsv(r.notes || [])

		const rowVals = [
			r.name || '',
			r.client_number ? String(r.client_number).trim() : '',
			assignee,
			r.status || 'in_progress',
			r.tier || '',
			Array.isArray(r.categories) ? r.categories.join('; ') : '',
			emailsStr,
			phonesStr,
			r.website || '',
			r.vat_id || '',
			addr.street || '',
			addr.city || '',
			addr.postal_code || '',
			addr.country_code || 'CZ',
			r.uuid || '',
			notesStr,
		]
		lines.push(rowVals.map(escapeCsvCell).join(','))
	}

	return lines.join('\r\n')
})

const loadLegacyOutput = async () => {
	legacyLoading.value = true
	try {
		legacyRows.value = await fetchLegacyTickyClients()
	} finally {
		legacyLoading.value = false
	}
}

const openLegacyReaderTab = () => {
	mode.value = 'legacy'
	if (legacyRows.value.length === 0) {
		loadLegacyOutput()
	}
}

const copyLegacyCsv = async () => {
	if (!legacyCsvOutput.value) return
	try {
		await navigator.clipboard.writeText(legacyCsvOutput.value)
		copyFeedback.value = '✓ Copied!'
		setTimeout(() => { copyFeedback.value = '' }, 2000)
	} catch {
		copyFeedback.value = 'Select & Ctrl+C'
	}
}

const downloadLegacyCsv = () => {
	if (!legacyCsvOutput.value) return
	const blob = new Blob(['\uFEFF' + legacyCsvOutput.value], { type: 'text/csv;charset=utf-8;' })
	const url = URL.createObjectURL(blob)
	const a = document.createElement('a')
	a.href = url
	a.download = 'legacy_ticky_crm.csv'
	a.click()
	URL.revokeObjectURL(url)
}

const sendLegacyToImportTab = () => {
	if (!legacyCsvOutput.value) return
	pastedText.value = legacyCsvOutput.value
	parseRawContent(legacyCsvOutput.value, false)
	mode.value = 'import'
}

const exportCsv = (templateOnly = false) => {
	const customCols = props.crmConfig.custom_columns || []
	const baseHeaders = [
		{ key: 'name', label: 'Name' },
		{ key: 'client_number', label: 'Client ID' },
		{ key: 'assigned_to', label: 'Assignee' },
		{ key: 'status', label: 'Status' },
		{ key: 'tier', label: 'Tier' },
		{ key: 'categories', label: 'Categories' },
		{ key: 'emails', label: 'Emails' },
		{ key: 'phones', label: 'Phones' },
		{ key: 'website', label: 'Website' },
		{ key: 'vat_id', label: 'VAT ID' },
		{ key: 'street', label: 'Street' },
		{ key: 'city', label: 'City' },
		{ key: 'postal_code', label: 'Postal Code' },
		{ key: 'country_code', label: 'Country' },
		{ key: 'uuid', label: 'UUID' },
		{ key: 'notes', label: 'Notes' },
	]

	const headerRow = [
		...baseHeaders.map(h => escapeCsvCell(h.label)),
		...customCols.map(c => escapeCsvCell(c.label)),
	].join(',')

	const rows = [headerRow]
	const list = templateOnly ? [] : getTargetClients()

	for (const c of list) {
		const addr = (c.addresses && c.addresses[0]) || {}
		const emailsStr = Array.isArray(c.emails) && c.emails.length
			? c.emails.map(e => (e.label && e.label !== 'Primary' ? `${e.label}:${e.email}` : e.email)).join('; ')
			: (c.contact_email || '')
		const phonesStr = Array.isArray(c.phones) && c.phones.length
			? c.phones.map(p => (p.label && p.label !== 'Primary' ? `${p.label}:${p.phone}` : p.phone)).join('; ')
			: (c.phone || '')
		const catsStr = Array.isArray(c.categories) ? c.categories.join('; ') : ''
		const notesStr = serializeNotesForCsv(c.notes || [])

		const baseValues = [
			c.name || '',
			c.client_number || '',
			c.assigned_to || '',
			c.status || '',
			c.tier || '',
			catsStr,
			emailsStr,
			phonesStr,
			c.website || '',
			c.vat_id || '',
			addr.street || '',
			addr.city || '',
			addr.postal_code || '',
			addr.country_code || '',
			c.uuid || '',
			notesStr,
		]

		const customValues = customCols.map(col => {
			const val = c.custom_fields ? c.custom_fields[col.id] : ''
			return val === undefined || val === null ? '' : val
		})

		rows.push([...baseValues, ...customValues].map(escapeCsvCell).join(','))
	}

	const csvContent = '\uFEFF' + rows.join('\r\n')
	const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
	const url = URL.createObjectURL(blob)
	const a = document.createElement('a')
	a.href = url
	a.download = templateOnly ? 'sticky_crm_template.csv' : `sticky_crm_export_${new Date().toISOString().slice(0, 10)}.csv`
	a.click()
	URL.revokeObjectURL(url)
}

const exportJson = () => {
	const list = getTargetClients()
	const blob = new Blob([JSON.stringify(list, null, 2)], { type: 'application/json' })
	const url = URL.createObjectURL(blob)
	const a = document.createElement('a')
	a.href = url
	a.download = `sticky_crm_export_${new Date().toISOString().slice(0, 10)}.json`
	a.click()
	URL.revokeObjectURL(url)
}

const parseCsvLine = (line, delimiter) => {
	const result = []
	let cur = ''
	let inQuotes = false
	for (let i = 0; i < line.length; i++) {
		const ch = line[i]
		if (inQuotes) {
			if (ch === '"' && line[i + 1] === '"') {
				cur += '"'
				i++
			} else if (ch === '"') {
				inQuotes = false
			} else {
				cur += ch
			}
		} else if (ch === '"') {
			inQuotes = true
		} else if (ch === delimiter) {
			result.push(cur.trim())
			cur = ''
		} else {
			cur += ch
		}
	}
	result.push(cur.trim())
	return result
}

const parseMultiContact = (raw, valueKey, defaultLabel) => {
	if (!raw) return []
	return String(raw)
		.split(/[;,]+/)
		.map(s => s.trim())
		.filter(Boolean)
		.map(part => {
			const colonIdx = part.indexOf(':')
			if (colonIdx > 0 && !part.startsWith('http')) {
				return {
					label: part.slice(0, colonIdx).trim() || defaultLabel,
					[valueKey]: part.slice(colonIdx + 1).trim(),
				}
			}
			return { label: defaultLabel, [valueKey]: part }
		})
}

const parseRawContent = (text, isJsonHint = false) => {
	parseError.value = ''
	importResult.value = null
	parsedRecords.value = []

	if (!text || !text.trim()) return

	try {
		const trimmed = text.replace(/^\uFEFF/, '').trim()
		if (isJsonHint || trimmed.startsWith('[')) {
			const data = JSON.parse(trimmed)
			if (!Array.isArray(data)) {
				throw new Error('JSON must contain an array of client objects.')
			}
			parsedRecords.value = data
			return
		}

		const lines = trimmed.split(/\r?\n/).filter(l => l.trim() !== '')
		if (lines.length < 2) {
			throw new Error('CSV must have a header row and at least one data row.')
		}

		const firstLine = lines[0]
		const delimiter = (firstLine.split(';').length > firstLine.split(',').length) ? ';' : ','
		const headers = parseCsvLine(firstLine, delimiter)
		const customCols = props.crmConfig.custom_columns || []

		const records = []
		for (let i = 1; i < lines.length; i++) {
			const values = parseCsvLine(lines[i], delimiter)
			const rec = {
				custom_fields: {},
				categories: [],
				emails: [],
				phones: [],
				notes: [],
			}
			const addr = { street: '', city: '', postal_code: '', country_code: 'CZ' }

			headers.forEach((h, idx) => {
				const val = values[idx] ?? ''
				const norm = h.toLowerCase().trim()

				if (['name', 'jméno', 'jmeno', 'firma', 'company', 'název', 'nazev'].includes(norm)) {
					rec.name = val
				} else if (['client id', 'client_number', 'id klienta', 'číslo klienta'].includes(norm)) {
					rec.client_number = val
				} else if (['assignee', 'assigned_to', 'řešitel', 'resitel', 'kdo to řeší', 'owner'].includes(norm)) {
					rec.assigned_to = val
				} else if (['status', 'stav'].includes(norm)) {
					rec.status = val || 'in_progress'
				} else if (['tier', 'stupeň', 'stupen', 'partnerství', 'level'].includes(norm)) {
					rec.tier = val.toLowerCase()
				} else if (['categories', 'category', 'kategorie', 'tags', 'tagy'].includes(norm)) {
					rec.categories = val.split(/[;,]+/).map(s => s.trim()).filter(Boolean)
				} else if (['emails', 'email', 'e-mail', 'contact_email'].includes(norm)) {
					rec.emails.push(...parseMultiContact(val, 'email', 'Primary'))
				} else if (norm === 'invoice_email') {
					rec.invoice_email = val
					if (val) rec.emails.push({ label: 'Billing', email: val })
				} else if (['phones', 'phone', 'telefon', 'mobil', 'tel'].includes(norm)) {
					rec.phones.push(...parseMultiContact(val, 'phone', 'Primary'))
				} else if (['type', 'typ'].includes(norm)) {
					rec.type = val || 'company'
				} else if (['website', 'web', 'url'].includes(norm)) {
					rec.website = val
				} else if (['vat id', 'vat_id', 'ičo', 'ico', 'dič', 'dic'].includes(norm)) {
					rec.vat_id = val
				} else if (norm === 'tax_number') {
					rec.tax_number = val
				} else if (norm === 'register_court') {
					rec.register_court = val
				} else if (norm === 'register_number') {
					rec.register_number = val
				} else if (['street', 'ulice'].includes(norm)) {
					addr.street = val
				} else if (['city', 'město', 'mesto'].includes(norm)) {
					addr.city = val
				} else if (['postal code', 'postal_code', 'psč', 'psc'].includes(norm)) {
					addr.postal_code = val
				} else if (['country', 'country_code', 'země'].includes(norm)) {
					addr.country_code = val || 'CZ'
				} else if (norm === 'uuid') {
					rec.uuid = val
				} else if (['notes', 'note', 'poznámky', 'poznamky', 'poznámka'].includes(norm)) {
					rec.notes.push(...parseNotesFromCsvCell(val))
				} else {
					const matchedCustom = customCols.find(
						c => c.id.toLowerCase() === norm || c.label.toLowerCase() === norm
					)
					if (matchedCustom && val !== '') {
						rec.custom_fields[matchedCustom.id] = matchedCustom.type === 'checkbox'
							? ['true', '1', 'yes', 'ano'].includes(val.toLowerCase())
							: val
					}
				}
			})

			if (!rec.assigned_to && rec.client_number) {
				rec.assigned_to = extractAssigneeFromClientNum(rec.client_number)
			}

			if (addr.street || addr.city) {
				rec.addresses = [addr]
			}
			if (rec.name) {
				records.push(rec)
			}
		}

		parsedRecords.value = records
	} catch (e) {
		parseError.value = e.message || 'Failed to parse content.'
	}
}

const handleFileSelect = async (event) => {
	const file = event.target.files?.[0]
	if (!file) return
	const text = await file.text()
	pastedText.value = text
	parseRawContent(text, file.name.toLowerCase().endsWith('.json'))
}

const handlePasteInput = () => {
	parseRawContent(pastedText.value, false)
}

const runImport = async () => {
	if (!parsedRecords.value.length) return
	importing.value = true
	parseError.value = ''
	try {
		const res = await importClients(parsedRecords.value)
		importResult.value = res
		if (res.clients) {
			emit('imported', res.clients)
		}
	} catch (e) {
		parseError.value = e.response?.data?.message || e.message || 'Import failed.'
	} finally {
		importing.value = false
	}
}
</script>

<style lang="scss" scoped>
.import-export-modal {
  padding: 20px 24px;
  width: 740px;
  max-width: 100%;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ie-header {
  display: flex;
  flex-direction: column;
  gap: 10px;
  border-bottom: 1px solid var(--color-border);
  padding-bottom: 12px;

  h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
  }
}

.ie-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.ie-tab-btn {
  padding: 6px 14px;
  border-radius: var(--border-radius-pill);
  border: 1px solid var(--color-border-dark);
  background: var(--color-main-background);
  color: var(--color-main-text);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;

  &.active {
    background: var(--color-primary-element);
    color: var(--color-primary-element-text);
    border-color: var(--color-primary-element);
  }
}

.ie-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.ie-desc {
  font-size: 13px;
  color: var(--color-text-maxcontrast);
  margin: 0;
}

.export-options {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border-radius: var(--border-radius);
  background: var(--color-background-hover);
}

.radio-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  cursor: pointer;
}

.export-buttons,
.legacy-controls {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.legacy-count-badge {
  font-size: 12px;
  color: var(--color-text-maxcontrast);
  margin-left: auto;
}

.file-drop-zone {
  padding: 12px 16px;
  border: 2px dashed var(--color-border-dark);
  border-radius: var(--border-radius-large);
  text-align: center;
}

.paste-csv-block {
  display: flex;
  flex-direction: column;
  gap: 6px;

  .paste-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
  }
}

.csv-textarea {
  width: 100%;
  box-sizing: border-box;
  font-family: monospace;
  font-size: 12px;
  padding: 10px;
  border-radius: var(--border-radius);
  border: 1px solid var(--color-border-dark);
  background: var(--color-main-background);
  color: var(--color-main-text);
  resize: vertical;
}

.legacy-output-area {
  min-height: 240px;
  white-space: pre;
  overflow-x: auto;
}

.preview-summary {
  font-size: 13px;
  margin-bottom: 6px;
}

.preview-table-wrap {
  max-height: 200px;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
}

.preview-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;

  th, td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--color-border-light);
    text-align: left;
  }

  th {
    background: var(--color-background-hover);
    font-weight: 600;
  }
}

.preview-more {
  padding: 6px 8px;
  font-size: 12px;
  color: var(--color-text-maxcontrast);
}

.import-actions {
  margin-top: 10px;
  display: flex;
  justify-content: flex-end;
}

.import-result-banner {
  padding: 10px 14px;
  border-radius: var(--border-radius);
  background: var(--color-background-hover);
  border-left: 4px solid var(--color-success);
  font-size: 13px;
}

.ie-error {
  color: var(--color-error);
  font-size: 13px;
}

.ie-footer {
  display: flex;
  justify-content: flex-end;
  border-top: 1px solid var(--color-border);
  padding-top: 12px;
}
</style>
