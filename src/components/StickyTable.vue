<template>
  <div class="sticky-table-container">
    <table class="sticky-table">
      <thead>
        <tr>
          <th
            v-for="col in resolvedColumns"
            :key="col.field"
            :style="{ maxWidth: col.maxWidth || 'none' }"
            :class="{
              'has-max-width': col.maxWidth && col.maxWidth !== 'none',
              'is-sortable': true,
              'is-sorted': sortField === col.field,
            }"
            @click="toggleSort(col.field)"
          >
            <div class="th-inner">
              <span class="cell-content">{{ col.header || col.label }}</span>
              <span class="sort-indicator">
                <template v-if="sortField === col.field">
                  {{ sortOrder === 'asc' ? '▲' : '▼' }}
                </template>
                <template v-else>
                  ⇅
                </template>
              </span>
            </div>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="sortedRows.length === 0">
          <td :colspan="resolvedColumns.length || 1" class="empty-table-cell">
            No matching clients or sponsors found.
          </td>
        </tr>
        <tr
          v-for="(row, rowIndex) in sortedRows"
          :key="getRowKey(row, rowIndex)"
          :class="{
            'is-selected': isRowSelected(row, rowIndex),
            'has-tier-bg': Boolean(getRowTierColor(row)),
          }"
          :style="getRowStyle(row)"
          @click="handleRowClick(row, rowIndex)"
        >
          <td
            v-for="col in resolvedColumns"
            :key="col.field"
            :style="{ maxWidth: col.maxWidth || 'none' }"
            :class="{ 'has-max-width': col.maxWidth && col.maxWidth !== 'none' }"
          >
            <div class="cell-content">
              <slot :name="col.field" :data="row">
                <!-- STATUS BADGE -->
                <template v-if="col.field === 'status'">
                  <span
                    v-if="row.status"
                    class="crm-pill"
                    :style="getBadgeStyle(getStatusColor(row.status))"
                  >
                    {{ getStatusLabel(row.status) }}
                  </span>
                </template>

                <!-- TIER BADGE -->
                <template v-else-if="col.field === 'tier'">
                  <span
                    v-if="row.tier"
                    class="crm-pill tier-pill"
                    :style="getBadgeStyle(getTierColor(row.tier))"
                  >
                    ★ {{ getTierLabel(row.tier) }}
                  </span>
                  <span v-else class="cell-muted">—</span>
                </template>

                <!-- CATEGORIES CHIPS -->
                <template v-else-if="col.field === 'categories'">
                  <div v-if="row.categories && row.categories.length" class="cell-chips">
                    <span
                      v-for="catId in row.categories"
                      :key="catId"
                      class="crm-chip"
                      :style="getBadgeStyle(getCategoryColor(catId))"
                    >
                      {{ getCategoryLabel(catId) }}
                    </span>
                  </div>
                  <span v-else class="cell-muted">—</span>
                </template>

                <!-- ASSIGNEE -->
                <template v-else-if="col.field === 'assigned_to'">
                  <span v-if="row.assigned_to" class="assignee-badge">
                    {{ row.assigned_to }}
                  </span>
                  <span v-else class="cell-muted">—</span>
                </template>

                <!-- MULTIPLE EMAILS -->
                <template v-else-if="col.field === 'emails' || col.field === 'contact_email'">
                  <div v-if="getRowEmails(row).length" class="multi-cell-list" :title="getRowEmails(row).map(e => `${e.label}: ${e.email}`).join('\n')">
                    <span class="primary-contact-val">{{ getRowEmails(row)[0].email }}</span>
                    <span v-if="getRowEmails(row).length > 1" class="extra-count-badge">
                      +{{ getRowEmails(row).length - 1 }}
                    </span>
                  </div>
                  <span v-else class="cell-muted">—</span>
                </template>

                <!-- MULTIPLE PHONES -->
                <template v-else-if="col.field === 'phones' || col.field === 'phone'">
                  <div v-if="getRowPhones(row).length" class="multi-cell-list" :title="getRowPhones(row).map(p => `${p.label}: ${p.phone}`).join('\n')">
                    <span class="primary-contact-val">{{ getRowPhones(row)[0].phone }}</span>
                    <span v-if="getRowPhones(row).length > 1" class="extra-count-badge">
                      +{{ getRowPhones(row).length - 1 }}
                    </span>
                  </div>
                  <span v-else class="cell-muted">—</span>
                </template>

                <!-- CUSTOM FIELDS & DEFAULT FIELDS -->
                <template v-else>
                  <template v-if="col.type === 'checkbox'">
                    <span>{{ getCellValue(row, col) ? '✓ Yes' : '—' }}</span>
                  </template>
                  <template v-else-if="col.type === 'url' && getCellValue(row, col)">
                    <a
                      :href="getCellValue(row, col)"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="table-link"
                      @click.stop
                    >
                      {{ getCellValue(row, col) }}
                    </a>
                  </template>
                  <template v-else>
                    {{ getCellValue(row, col) || '—' }}
                  </template>
                </template>
              </slot>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { useSlots, computed, ref, watch } from 'vue'
import { DEFAULT_CRM_CONFIG } from '../services/clientService'

const props = defineProps({
	value: { type: Array, required: true },
	columnsConfig: { type: Array, default: null },
	crmConfig: { type: Object, default: () => DEFAULT_CRM_CONFIG },
})

const emit = defineEmits(['row-click'])
const slots = useSlots()

const selectedRow = ref(null)
const sortField = ref('name')
const sortOrder = ref('asc')

const resolvedColumns = computed(() => {
	if (Array.isArray(props.columnsConfig) && props.columnsConfig.length > 0) {
		return props.columnsConfig
			.filter(c => c.visible !== false)
			.map(c => ({
				header: c.label || c.header || c.field,
				label: c.label || c.header || c.field,
				field: c.field,
				isCustom: Boolean(c.isCustom),
				type: c.type || 'text',
				maxWidth: c.maxWidth || (c.field === 'name' ? '240px' : '220px'),
			}))
	}
	if (slots.default) {
		return slots.default().map(vnode => ({
			header: vnode.props?.header,
			label: vnode.props?.header,
			field: vnode.props?.field,
			isCustom: false,
			type: 'text',
			maxWidth: vnode.props?.['max-width'] || vnode.props?.maxWidth || 'none',
		}))
	}
	return []
})

const getCellValue = (row, col) => {
	if (!row) return ''
	if (col.isCustom || (col.field && col.field.startsWith('custom_'))) {
		return row.custom_fields?.[col.field] ?? ''
	}
	return row[col.field] ?? ''
}

const getRowEmails = (row) => {
	if (Array.isArray(row.emails) && row.emails.length > 0) {
		return row.emails.filter(e => e.email)
	}
	if (row.contact_email) {
		return [{ label: 'Primary', email: row.contact_email }]
	}
	return []
}

const getRowPhones = (row) => {
	if (Array.isArray(row.phones) && row.phones.length > 0) {
		return row.phones.filter(p => p.phone)
	}
	if (row.phone) {
		return [{ label: 'Primary', phone: row.phone }]
	}
	return []
}

const toggleSort = (field) => {
	if (sortField.value === field) {
		sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
	} else {
		sortField.value = field
		sortOrder.value = 'asc'
	}
}

const getSortableString = (row, field) => {
	if (!row) return ''
	if (field === 'emails' || field === 'contact_email') {
		return getRowEmails(row)[0]?.email || ''
	}
	if (field === 'phones' || field === 'phone') {
		return getRowPhones(row)[0]?.phone || ''
	}
	if (field === 'categories') {
		return Array.isArray(row.categories) ? row.categories.join(', ') : ''
	}
	if (field.startsWith('custom_')) {
		const v = row.custom_fields?.[field]
		return v === undefined || v === null ? '' : String(v)
	}
	const val = row[field]
	return val === undefined || val === null ? '' : String(val)
}

const sortedRows = computed(() => {
	const list = [...(props.value || [])]
	if (!sortField.value) return list

	const dir = sortOrder.value === 'asc' ? 1 : -1
	const field = sortField.value

	return list.sort((a, b) => {
		const va = getSortableString(a, field)
		const vb = getSortableString(b, field)
		const numA = Number(va)
		const numB = Number(vb)
		if (va !== '' && vb !== '' && !Number.isNaN(numA) && !Number.isNaN(numB)) {
			return (numA - numB) * dir
		}
		return va.localeCompare(vb, undefined, { numeric: true, sensitivity: 'base' }) * dir
	})
})

const getTierObj = (tierId) => {
	if (!tierId) return null
	return (props.crmConfig?.tiers || []).find(t => t.id === tierId) || null
}

const getTierLabel = (tierId) => {
	return getTierObj(tierId)?.label || tierId
}

const getTierColor = (tierId) => {
	return getTierObj(tierId)?.color || '#64748b'
}

const getStatusObj = (statusId) => {
	if (!statusId) return null
	return (props.crmConfig?.statuses || []).find(s => s.id === statusId) || null
}

const getStatusLabel = (statusId) => {
	return getStatusObj(statusId)?.label || statusId
}

const getStatusColor = (statusId) => {
	return getStatusObj(statusId)?.color || '#6b7280'
}

const getCategoryObj = (catId) => {
	if (!catId) return null
	return (props.crmConfig?.categories || []).find(c => c.id === catId) || null
}

const getCategoryLabel = (catId) => {
	return getCategoryObj(catId)?.label || catId
}

const getCategoryColor = (catId) => {
	return getCategoryObj(catId)?.color || '#0ea5e9'
}

const getBadgeStyle = (hexColor) => {
	const c = hexColor || '#64748b'
	return {
		backgroundColor: `color-mix(in srgb, ${c} 18%, var(--color-main-background))`,
		color: 'var(--color-main-text)',
		borderColor: `color-mix(in srgb, ${c} 55%, transparent)`,
	}
}

const getRowTierColor = (row) => {
	if (!props.crmConfig?.row_coloring_enabled || !row?.tier) {
		return null
	}
	return getTierObj(row.tier)?.color || null
}

const getRowStyle = (row) => {
	const color = getRowTierColor(row)
	if (!color) return {}
	return {
		'--row-tier-color': color,
		'--row-tier-bg': `color-mix(in srgb, ${color} 13%, var(--color-main-background))`,
		'--row-tier-bg-hover': `color-mix(in srgb, ${color} 22%, var(--color-main-background))`,
	}
}

const handleRowClick = (row) => {
	selectedRow.value = row
	emit('row-click', row)
}

const getRowKey = (row, index) => {
	if (row && typeof row === 'object') {
		return row.uuid || row.id || index
	}
	return index
}

const isRowSelected = (row) => {
	if (!selectedRow.value) return false

	if (row.uuid && selectedRow.value.uuid) return row.uuid === selectedRow.value.uuid
	if (row.id && selectedRow.value.id) return row.id === selectedRow.value.id

	return row === selectedRow.value
}

watch(() => props.value, () => {
	selectedRow.value = null
}, { deep: false })
</script>

<style lang="scss" scoped>
.sticky-table-container {
  width: 100%;
  overflow-x: auto;
}

.sticky-table {
  width: 100%;
  border-collapse: collapse;
  position: relative;
  table-layout: auto;

  th, td {
    padding: 10px 12px;
    vertical-align: middle;

    &.has-max-width {
      overflow: hidden;

      .cell-content {
        display: block;
        width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
    }
  }

  th {
    text-align: left;
    color: var(--color-text-maxcontrast);
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    position: sticky;
    top: 0;
    background-color: var(--color-main-background);
    border-bottom: 2px solid var(--color-border);
    user-select: none;

    &.is-sortable {
      cursor: pointer;

      &:hover {
        color: var(--color-main-text);
        background-color: var(--color-background-hover);
      }
    }

    &.is-sorted {
      color: var(--color-primary-element);
    }

    .th-inner {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .sort-indicator {
      font-size: 10px;
      opacity: 0.65;
      flex-shrink: 0;
    }
  }

  td {
    border-bottom: 1px solid var(--color-border-light);
    color: var(--color-main-text);
    font-size: 13px;
  }

  .empty-table-cell {
    text-align: center;
    padding: 32px 16px;
    color: var(--color-text-maxcontrast);
    font-style: italic;
  }

  tr {
    cursor: pointer;
    transition: background-color 0.12s ease;

    &.has-tier-bg {
      td {
        background-color: var(--row-tier-bg);
      }
      td:first-child {
        box-shadow: inset 4px 0 0 var(--row-tier-color);
      }

      &:hover td {
        background-color: var(--row-tier-bg-hover);
      }
    }

    &:not(.has-tier-bg):hover {
      td {
        background-color: var(--color-background-hover);
      }
    }

    &.is-selected {
      td {
        background-color: var(--color-background-hover);
        color: var(--color-main-text);
        font-weight: 600;
      }
      td:first-child {
        box-shadow: inset 4px 0 0 var(--color-primary-element);
      }
    }
  }
}

.crm-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 9px;
  border-radius: var(--border-radius-pill);
  border: 1px solid transparent;
  font-size: 11.5px;
  font-weight: 600;
  line-height: 1.4;
  white-space: nowrap;
}

.cell-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.crm-chip {
  display: inline-block;
  padding: 1px 7px;
  border-radius: var(--border-radius-pill);
  border: 1px solid transparent;
  font-size: 11px;
  font-weight: 500;
  white-space: nowrap;
}

.assignee-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: var(--border-radius);
  background: var(--color-background-dark);
  color: var(--color-main-text);
  font-size: 12px;
  font-weight: 500;
}

.multi-cell-list {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  max-width: 100%;

  .primary-contact-val {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .extra-count-badge {
    font-size: 10.5px;
    font-weight: 600;
    padding: 1px 6px;
    border-radius: var(--border-radius-pill);
    background: var(--color-primary-light);
    color: var(--color-primary-text);
    flex-shrink: 0;
  }
}

.cell-muted {
  color: var(--color-text-maxcontrast);
  opacity: 0.6;
}

.table-link {
  color: var(--color-primary-element);
  text-decoration: underline;
}
</style>