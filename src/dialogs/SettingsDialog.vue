<template>
  <NcModal size="large" name="CRM Global Settings" @close="emit('close')">
    <div class="sticky-modal-settings">
      <div class="settings-header">
        <h3>{{ t('sticky_crm', 'settings_global_title') }}</h3>
        <div class="settings-tabs">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            class="settings-tab-btn"
            :class="{ active: activeTab === tab.id }"
            @click="activeTab = tab.id"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>

      <div v-if="loading" class="settings-loading">
        {{ t('sticky_crm', 'settings_loading') }}
      </div>

      <div v-else class="settings-body">
        <!-- TAB 1: COLUMNS & CUSTOM FIELDS -->
        <div v-if="activeTab === 'columns'" class="settings-section">
          <p class="settings-description">
            Configure which columns are visible in the table, their order, and create custom fields for all sponsors/clients.
          </p>

          <div class="custom-column-creator">
            <h4>Add Custom Column</h4>
            <div class="creator-row">
              <NcTextField
                v-model="newCustomCol.label"
                label="Column Name (e.g. Budget, Contract Date)"
                placeholder="Enter column name..."
              />
              <div class="native-select-wrap">
                <label>Field Type</label>
                <select v-model="newCustomCol.type" class="native-select">
                  <option value="text">Text</option>
                  <option value="number">Number</option>
                  <option value="select">Dropdown (Select)</option>
                  <option value="date">Date</option>
                  <option value="checkbox">Checkbox (Yes/No)</option>
                  <option value="url">URL / Link</option>
                </select>
              </div>
              <NcTextField
                v-if="newCustomCol.type === 'select'"
                v-model="newCustomCol.optionsRaw"
                label="Options (comma-separated)"
                placeholder="Option A, Option B, Option C"
              />
              <NcButton type="secondary" :disabled="!newCustomCol.label.trim()" @click="addCustomColumn">
                <template #icon>
                  <IconPlus :size="16" />
                </template>
                Add Column
              </NcButton>
            </div>
          </div>

          <h4>Table Columns Order & Visibility</h4>
          <div class="columns-list">
            <div
              v-for="(col, idx) in localConfig.columns"
              :key="col.field"
              class="column-item"
            >
              <label class="col-visibility">
                <input v-model="col.visible" type="checkbox">
                <span class="col-badge" :class="{ custom: col.isCustom }">
                  {{ col.isCustom ? 'Custom (' + col.type + ')' : 'Built-in' }}
                </span>
              </label>

              <input
                v-model="col.label"
                type="text"
                class="col-label-input"
                placeholder="Column label"
              >

              <div class="col-actions">
                <button
                  type="button"
                  class="icon-btn"
                  :disabled="idx === 0"
                  title="Move up"
                  @click="moveItem(localConfig.columns, idx, -1)"
                >
                  ▲
                </button>
                <button
                  type="button"
                  class="icon-btn"
                  :disabled="idx === localConfig.columns.length - 1"
                  title="Move down"
                  @click="moveItem(localConfig.columns, idx, 1)"
                >
                  ▼
                </button>
                <button
                  v-if="col.isCustom"
                  type="button"
                  class="icon-btn danger"
                  title="Delete custom column"
                  @click="removeCustomColumn(col.field)"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 2: PARTNERSHIP TIERS & COLORS -->
        <div v-if="activeTab === 'tiers'" class="settings-section">
          <div class="coloring-toggle-box">
            <label class="checkbox-row">
              <input v-model="localConfig.row_coloring_enabled" type="checkbox">
              <strong>Color table row backgrounds based on Partnership Tier</strong>
            </label>
            <p class="settings-description">
              When enabled, each sponsor row in the table receives a subtle background tint and left border matching its partnership tier (Starter, Bronze, Silver, Gold, General).
            </p>
          </div>

          <h4>Partnership Tiers</h4>
          <div class="tag-items-list">
            <div
              v-for="(tier, idx) in localConfig.tiers"
              :key="tier.id"
              class="tag-item-row"
            >
              <input v-model="tier.color" type="color" class="color-picker">
              <input v-model="tier.label" type="text" class="col-label-input" placeholder="Tier name">
              <code class="tag-id-code">{{ tier.id }}</code>
              <div class="col-actions">
                <button type="button" class="icon-btn" :disabled="idx === 0" @click="moveItem(localConfig.tiers, idx, -1)">
                  ▲
                </button>
                <button type="button" class="icon-btn" :disabled="idx === localConfig.tiers.length - 1" @click="moveItem(localConfig.tiers, idx, 1)">
                  ▼
                </button>
                <button type="button" class="icon-btn danger" @click="localConfig.tiers.splice(idx, 1)">
                  ✕
                </button>
              </div>
            </div>
          </div>

          <div class="add-tag-row">
            <input v-model="newTier.color" type="color" class="color-picker">
            <NcTextField v-model="newTier.label" label="New Tier Name (e.g. Platinum)" />
            <NcButton type="secondary" :disabled="!newTier.label.trim()" @click="addTier">
              <template #icon>
                <IconPlus :size="16" />
              </template>
              Add Tier
            </NcButton>
          </div>
        </div>

        <!-- TAB 3: CATEGORIES & STATUSES -->
        <div v-if="activeTab === 'tags'" class="settings-section">
          <div class="two-col-settings">
            <div>
              <h4>Sponsorship Categories (Obrábění, Peníze, Slevy...)</h4>
              <div class="tag-items-list">
                <div
                  v-for="(cat, idx) in localConfig.categories"
                  :key="cat.id"
                  class="tag-item-row"
                >
                  <input v-model="cat.color" type="color" class="color-picker">
                  <input v-model="cat.label" type="text" class="col-label-input">
                  <button type="button" class="icon-btn danger" @click="localConfig.categories.splice(idx, 1)">
                    ✕
                  </button>
                </div>
              </div>
              <div class="add-tag-row">
                <input v-model="newCategory.color" type="color" class="color-picker">
                <NcTextField v-model="newCategory.label" label="New Category (e.g. 3D Tisk)" />
                <NcButton type="secondary" :disabled="!newCategory.label.trim()" @click="addCategory">
                  <template #icon>
                    <IconPlus :size="16" />
                  </template>
                  Add
                </NcButton>
              </div>
            </div>

            <div>
              <h4>Sponsorship Statuses (V řešení, Podpoří, Nepodpoří...)</h4>
              <div class="tag-items-list">
                <div
                  v-for="(st, idx) in localConfig.statuses"
                  :key="st.id"
                  class="tag-item-row"
                >
                  <input v-model="st.color" type="color" class="color-picker">
                  <input v-model="st.label" type="text" class="col-label-input">
                  <button type="button" class="icon-btn danger" @click="localConfig.statuses.splice(idx, 1)">
                    ✕
                  </button>
                </div>
              </div>
              <div class="add-tag-row">
                <input v-model="newStatus.color" type="color" class="color-picker">
                <NcTextField v-model="newStatus.label" label="New Status" />
                <NcButton type="secondary" :disabled="!newStatus.label.trim()" @click="addStatus">
                  <template #icon>
                    <IconPlus :size="16" />
                  </template>
                  Add
                </NcButton>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 4: ACCESS & UTILITIES -->
        <div v-if="activeTab === 'access'" class="settings-section">
          <h4>Access Permissions</h4>
          <p class="settings-description">
            {{ t('sticky_crm', 'settings_description') }}
          </p>

          <div class="setting-row">
            <NcSelect
              v-bind="groupSelectConfig"
              v-model="groupSelectConfig.value"
            />
          </div>

          <div class="setting-row">
            <NcSelect
              v-bind="userSelectConfig"
              v-model="userSelectConfig.value"
            />
          </div>

          <hr class="settings-divider">

          <h4>Data Utilities</h4>
          <p class="settings-description">
            If your team previously wrote their names into the Client ID column (e.g. <code>Mari-002</code>, <code>Jedlicka_011</code>), click below to automatically extract those prefixes into the <strong>Assignee</strong> column.
          </p>
          <div class="utility-actions">
            <NcButton type="secondary" :disabled="extracting" @click="handleExtractAssignees">
              {{ extracting ? 'Extracting...' : 'Extract Assignees from Client IDs' }}
            </NcButton>
            <span v-if="extractMessage" class="utility-msg">{{ extractMessage }}</span>
          </div>
        </div>
      </div>

      <div class="settings-footer">
        <p v-if="error" class="settings-error">
          {{ error }}
        </p>
        <div class="settings-actions">
          <NcButton type="tertiary" @click="emit('close')">
            {{ t('sticky_crm', 'client_cancel') }}
          </NcButton>
          <NcButton type="primary" :disabled="saving || loading" @click="handleSave">
            <template #icon>
              <IconCheck :size="16" />
            </template>
            {{ saving ? t('sticky_crm', 'settings_saving') : t('sticky_crm', 'settings_save') }}
          </NcButton>
        </div>
      </div>
    </div>
  </NcModal>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { t } from '@nextcloud/l10n'
import { NcModal, NcButton, NcTextField, NcSelect } from '@nextcloud/vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import {
	getSettingsData,
	saveSettingsData,
	extractAssigneesFromClientNumbers,
	DEFAULT_CRM_CONFIG,
} from '../services/clientService'

const emit = defineEmits(['close', 'settings-saved', 'clients-updated'])

const activeTab = ref('columns')
const tabs = [
	{ id: 'columns', label: 'Columns & Custom Fields' },
	{ id: 'tiers', label: 'Partnership Tiers & Colors' },
	{ id: 'tags', label: 'Categories & Statuses' },
	{ id: 'access', label: 'Permissions & Tools' },
]

const loading = ref(false)
const saving = ref(false)
const extracting = ref(false)
const extractMessage = ref('')
const error = ref('')

const localConfig = reactive(JSON.parse(JSON.stringify(DEFAULT_CRM_CONFIG)))

const newCustomCol = reactive({
	label: '',
	type: 'text',
	optionsRaw: '',
})

const newTier = reactive({ label: '', color: '#14b8a6' })
const newCategory = reactive({ label: '', color: '#0ea5e9' })
const newStatus = reactive({ label: '', color: '#6366f1' })

const groupSelectConfig = reactive({
	inputLabel: t('sticky_crm', 'settings_groups_label'),
	multiple: true,
	closeOnSelect: false,
	placeholder: t('sticky_crm', 'settings_groups_placeholder'),
	options: [],
	value: [],
})

const userSelectConfig = reactive({
	inputLabel: t('sticky_crm', 'settings_users_label'),
	multiple: true,
	closeOnSelect: false,
	placeholder: t('sticky_crm', 'settings_users_placeholder'),
	options: [],
	value: [],
})

const slugify = (str) => {
	return str
		.toLowerCase()
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.replace(/[^a-z0-9]+/g, '_')
		.replace(/^_+|_+$/g, '') || ('field_' + Date.now())
}

const moveItem = (arr, index, delta) => {
	const target = index + delta
	if (target < 0 || target >= arr.length) return
	const temp = arr[index]
	arr[index] = arr[target]
	arr[target] = temp
}

const addCustomColumn = () => {
	const label = newCustomCol.label.trim()
	if (!label) return

	const id = 'custom_' + slugify(label)
	if (localConfig.columns.some(c => c.field === id)) {
		error.value = 'A column with a similar identifier already exists.'
		return
	}

	const options = newCustomCol.type === 'select'
		? newCustomCol.optionsRaw.split(',').map(s => s.trim()).filter(Boolean)
		: []

	const customDef = {
		id,
		label,
		type: newCustomCol.type,
		options,
	}

	localConfig.custom_columns.push(customDef)
	localConfig.columns.push({
		field: id,
		label,
		visible: true,
		isCustom: true,
		type: newCustomCol.type,
		options,
	})

	newCustomCol.label = ''
	newCustomCol.type = 'text'
	newCustomCol.optionsRaw = ''
	error.value = ''
}

const removeCustomColumn = (fieldId) => {
	localConfig.columns = localConfig.columns.filter(c => c.field !== fieldId)
	localConfig.custom_columns = localConfig.custom_columns.filter(c => c.id !== fieldId)
}

const addTier = () => {
	const label = newTier.label.trim()
	if (!label) return
	const id = slugify(label)
	if (!localConfig.tiers.some(t => t.id === id)) {
		localConfig.tiers.push({ id, label, color: newTier.color })
	}
	newTier.label = ''
}

const addCategory = () => {
	const label = newCategory.label.trim()
	if (!label) return
	const id = slugify(label)
	if (!localConfig.categories.some(c => c.id === id)) {
		localConfig.categories.push({ id, label, color: newCategory.color })
	}
	newCategory.label = ''
}

const addStatus = () => {
	const label = newStatus.label.trim()
	if (!label) return
	const id = slugify(label)
	if (!localConfig.statuses.some(s => s.id === id)) {
		localConfig.statuses.push({ id, label, color: newStatus.color })
	}
	newStatus.label = ''
}

const loadSettings = async () => {
	loading.value = true
	error.value = ''

	try {
		const data = await getSettingsData()

		groupSelectConfig.options = Object.values(data.all_groups || [])
		userSelectConfig.options = Object.values(data.all_users || [])

		groupSelectConfig.value = groupSelectConfig.options.filter(g => (data.allowed_groups || []).includes(g.id))
		userSelectConfig.value = userSelectConfig.options.filter(u => (data.allowed_users || []).includes(u.id))

		if (data.crm_config) {
			Object.assign(localConfig, JSON.parse(JSON.stringify(data.crm_config)))
		}
	} catch (e) {
		error.value = t('sticky_crm', 'settings_error_load')
		console.error(e)
	} finally {
		loading.value = false
	}
}

const handleExtractAssignees = async () => {
	extracting.value = true
	extractMessage.value = ''
	try {
		const res = await extractAssigneesFromClientNumbers(false)
		extractMessage.value = `Extracted assignees for ${res.updated} client(s).`
		if (res.clients) {
			emit('clients-updated', res.clients)
		}
	} catch (e) {
		error.value = e.message || 'Failed to extract assignees.'
	} finally {
		extracting.value = false
	}
}

const handleSave = async () => {
	saving.value = true
	error.value = ''

	try {
		// Sync custom_columns labels with columns array
		localConfig.custom_columns.forEach(cc => {
			const matchingCol = localConfig.columns.find(c => c.field === cc.id)
			if (matchingCol) {
				cc.label = matchingCol.label
			}
		})

		const res = await saveSettingsData({
			groups: groupSelectConfig.value.map(g => g.id),
			users: userSelectConfig.value.map(u => u.id),
			crm_config: localConfig,
		})
		emit('settings-saved', res.crm_config || JSON.parse(JSON.stringify(localConfig)))
		emit('close')
	} catch (e) {
		error.value = t('sticky_crm', 'settings_error_save')
		console.error(e)
	} finally {
		saving.value = false
	}
}

onMounted(() => loadSettings())
</script>

<style lang="scss" scoped>
.sticky-modal-settings {
  padding: 20px 24px;
  width: 780px;
  max-width: 100%;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.settings-header {
  display: flex;
  flex-direction: column;
  gap: 12px;
  border-bottom: 1px solid var(--color-border);
  padding-bottom: 12px;

  h3 {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    color: var(--color-main-text);
  }
}

.settings-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.settings-tab-btn {
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

.settings-body {
  max-height: 62vh;
  overflow-y: auto;
  padding-right: 4px;
}

.settings-section {
  display: flex;
  flex-direction: column;
  gap: 14px;

  h4 {
    margin: 8px 0 4px;
    font-size: 14px;
    font-weight: 600;
    color: var(--color-main-text);
  }
}

.settings-description {
  font-size: 13px;
  color: var(--color-text-maxcontrast);
  line-height: 1.4;
  margin: 0;
}

.custom-column-creator {
  background: var(--color-background-hover);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  padding: 12px 16px;

  .creator-row {
    display: grid;
    grid-template-columns: 1fr 160px auto;
    gap: 12px;
    align-items: end;
  }
}

.native-select-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;

  label {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    font-weight: 500;
  }

  .native-select {
    height: 34px;
    border-radius: var(--border-radius);
    border: 1px solid var(--color-border-dark);
    background: var(--color-main-background);
    color: var(--color-main-text);
    padding: 0 8px;
  }
}

.columns-list,
.tag-items-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.column-item,
.tag-item-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
  background: var(--color-main-background);
}

.col-visibility {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 120px;
  cursor: pointer;
}

.col-badge {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: var(--border-radius-pill);
  background: var(--color-background-dark);
  color: var(--color-text-maxcontrast);

  &.custom {
    background: var(--color-primary-light);
    color: var(--color-primary-text);
  }
}

.col-label-input {
  flex: 1;
  height: 30px;
  padding: 0 8px;
  border: 1px solid var(--color-border-dark);
  border-radius: var(--border-radius);
  background: var(--color-main-background);
  color: var(--color-main-text);
}

.color-picker {
  width: 34px;
  height: 30px;
  padding: 2px;
  border: 1px solid var(--color-border-dark);
  border-radius: var(--border-radius);
  background: transparent;
  cursor: pointer;
}

.tag-id-code {
  font-size: 11px;
  color: var(--color-text-maxcontrast);
  background: var(--color-background-hover);
  padding: 2px 6px;
  border-radius: 4px;
}

.col-actions {
  display: flex;
  gap: 4px;
}

.icon-btn {
  width: 28px;
  height: 28px;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
  background: var(--color-background-hover);
  color: var(--color-main-text);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;

  &:disabled {
    opacity: 0.35;
    cursor: default;
  }

  &.danger {
    color: var(--color-error);
  }
}

.add-tag-row {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  margin-top: 8px;
}

.two-col-settings {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.coloring-toggle-box {
  padding: 12px 14px;
  border-radius: var(--border-radius-large);
  background: var(--color-background-hover);
  border: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  gap: 6px;

  .checkbox-row {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
  }
}

.settings-divider {
  border: none;
  border-top: 1px solid var(--color-border);
  margin: 8px 0;
}

.utility-actions {
  display: flex;
  align-items: center;
  gap: 12px;

  .utility-msg {
    font-size: 13px;
    color: var(--color-success);
    font-weight: 500;
  }
}

.settings-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-top: 1px solid var(--color-border);
  padding-top: 12px;
}

.settings-actions {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

.settings-error {
  color: var(--color-error);
  font-size: 13px;
  margin: 0;
}

.settings-loading {
  color: var(--color-text-maxcontrast);
  font-size: 13px;
  padding: 24px 0;
  text-align: center;
}

@media (max-width: 720px) {
  .two-col-settings {
    grid-template-columns: 1fr;
  }
  .custom-column-creator .creator-row {
    grid-template-columns: 1fr;
  }
}
</style>