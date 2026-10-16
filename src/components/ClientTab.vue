<template>
  <div class="sidebar-content">
    <div v-if="errorMessage" class="error-banner">
      {{ errorMessage }}
    </div>

    <div class="sidebar-form">
      <!-- MASTER DATA & SPONSORSHIP -->
      <fieldset class="sidebar-fieldset">
        <legend>{{ t('sticky_crm', 'section_master_data') }}</legend>
        <NcTextField
          v-model="editableClient.name"
          :label="t('sticky_crm', 'field_name')"
          :disabled="isLoading"
        />

        <div class="field-group">
          <label class="field-label">{{ t('sticky_crm', 'field_assigned_to') }}</label>
          <div class="assignee-input-row">
            <input
              v-model="editableClient.assigned_to"
              type="text"
              list="sticky-crm-users-datalist"
              class="nc-input"
              :placeholder="t('sticky_crm', 'placeholder_assigned_to')"
              :disabled="isLoading"
            >
            <datalist id="sticky-crm-users-datalist">
              <option v-for="u in availableUsers" :key="u.id" :value="u.label">
                {{ u.id }}
              </option>
            </datalist>
          </div>
        </div>

        <div class="field-group">
          <label class="field-label">{{ t('sticky_crm', 'field_status') }}</label>
          <select v-model="editableClient.status" class="nc-select" :disabled="isLoading">
            <option v-for="st in crmConfig.statuses" :key="st.id" :value="st.id">
              {{ st.label }}
            </option>
          </select>
        </div>

        <div class="field-group">
          <label class="field-label">{{ t('sticky_crm', 'field_tier') }}</label>
          <select v-model="editableClient.tier" class="nc-select" :disabled="isLoading">
            <option value="">— None —</option>
            <option v-for="tr in crmConfig.tiers" :key="tr.id" :value="tr.id">
              {{ tr.label }}
            </option>
          </select>
        </div>

        <div class="field-group">
          <label class="field-label">{{ t('sticky_crm', 'field_categories') }}</label>
          <div class="category-chips-selector">
            <button
              v-for="cat in crmConfig.categories"
              :key="cat.id"
              type="button"
              class="cat-chip-btn"
              :class="{ selected: isCategorySelected(cat.id) }"
              :style="isCategorySelected(cat.id) ? { backgroundColor: cat.color, borderColor: cat.color, color: '#fff' } : { borderColor: cat.color }"
              :disabled="isLoading"
              @click="toggleCategory(cat.id)"
            >
              {{ cat.label }}
            </button>
          </div>
        </div>

        <NcTextField
          v-model="editableClient.client_number"
          :label="t('sticky_crm', 'field_client_number')"
          :disabled="isLoading"
        />

        <div class="field-group">
          <label class="field-label">{{ t('sticky_crm', 'field_type') }}</label>
          <select v-model="editableClient.type" class="nc-select" :disabled="isLoading">
            <option value="company">{{ t('sticky_crm', 'type_company') }}</option>
            <option value="person">{{ t('sticky_crm', 'type_person') }}</option>
          </select>
        </div>
      </fieldset>

      <!-- MULTIPLE EMAILS & PHONES + WEBSITE -->
      <fieldset class="sidebar-fieldset">
        <legend>{{ t('sticky_crm', 'section_contact') }}</legend>

        <div class="multi-contact-block">
          <div class="multi-header">
            <label class="field-label">{{ t('sticky_crm', 'column_email') }}</label>
            <button type="button" class="link-add-btn" :disabled="isLoading" @click="addEmail">
              + {{ t('sticky_crm', 'btn_add_email') }}
            </button>
          </div>
          <div
            v-for="(item, idx) in editableClient.emails"
            :key="'em-' + idx"
            class="multi-item-row"
          >
            <input
              v-model="item.label"
              type="text"
              class="nc-input multi-label-input"
              placeholder="Label"
              :disabled="isLoading"
            >
            <input
              v-model="item.email"
              type="email"
              class="nc-input multi-value-input"
              placeholder="email@example.com"
              :disabled="isLoading"
            >
            <button
              type="button"
              class="multi-remove-btn"
              :disabled="isLoading"
              title="Remove email"
              @click="removeEmail(idx)"
            >
              ✕
            </button>
          </div>
        </div>

        <div class="multi-contact-block">
          <div class="multi-header">
            <label class="field-label">{{ t('sticky_crm', 'column_phone') }}</label>
            <button type="button" class="link-add-btn" :disabled="isLoading" @click="addPhone">
              + {{ t('sticky_crm', 'btn_add_phone') }}
            </button>
          </div>
          <div
            v-for="(item, idx) in editableClient.phones"
            :key="'ph-' + idx"
            class="multi-item-row"
          >
            <input
              v-model="item.label"
              type="text"
              class="nc-input multi-label-input"
              placeholder="Label"
              :disabled="isLoading"
            >
            <input
              v-model="item.phone"
              type="tel"
              class="nc-input multi-value-input"
              placeholder="+420 ..."
              :disabled="isLoading"
            >
            <button
              type="button"
              class="multi-remove-btn"
              :disabled="isLoading"
              title="Remove phone"
              @click="removePhone(idx)"
            >
              ✕
            </button>
          </div>
        </div>

        <NcTextField
          v-model="editableClient.website"
          :label="t('sticky_crm', 'field_website')"
          type="url"
          :disabled="isLoading"
        />
      </fieldset>

      <!-- CUSTOM FIELDS -->
      <fieldset v-if="crmConfig.custom_columns && crmConfig.custom_columns.length > 0" class="sidebar-fieldset">
        <legend>{{ t('sticky_crm', 'section_custom_fields') }}</legend>
        <div
          v-for="col in crmConfig.custom_columns"
          :key="col.id"
          class="field-group"
        >
          <template v-if="col.type === 'checkbox'">
            <label class="checkbox-inline">
              <input
                v-model="editableClient.custom_fields[col.id]"
                type="checkbox"
                :disabled="isLoading"
              >
              <span>{{ col.label }}</span>
            </label>
          </template>
          <template v-else-if="col.type === 'select'">
            <label class="field-label">{{ col.label }}</label>
            <select
              v-model="editableClient.custom_fields[col.id]"
              class="nc-select"
              :disabled="isLoading"
            >
              <option value="">—</option>
              <option v-for="opt in (col.options || [])" :key="opt" :value="opt">
                {{ opt }}
              </option>
            </select>
          </template>
          <template v-else>
            <label class="field-label">{{ col.label }}</label>
            <input
              v-model="editableClient.custom_fields[col.id]"
              :type="col.type === 'number' ? 'number' : (col.type === 'date' ? 'date' : (col.type === 'url' ? 'url' : 'text'))"
              class="nc-input"
              :disabled="isLoading"
            >
          </template>
        </div>
      </fieldset>

      <!-- ADDRESSES -->
      <fieldset class="sidebar-fieldset">
        <legend>{{ t('sticky_crm', 'section_addresses') }}</legend>

        <div v-for="(address, index) in editableClient.addresses" :key="index" class="sidebar-address-item">
          <div class="address-item-header">
            <span />
            <NcButton type="error" :disabled="isLoading" @click="removeAddress(index)">
              <template #icon>
                <IconDelete :size="14" />
              </template>
            </NcButton>
          </div>

          <div class="address-fields-vertical">
            <NcTextField
              v-model="address.label"
              :label="t('sticky_crm', 'field_address_label')"
              :disabled="isLoading"
            />
            <NcTextField
              v-model="address.street"
              :label="t('sticky_crm', 'field_street')"
              :disabled="isLoading"
            />
            <NcTextField
              v-model="address.house_number"
              :label="t('sticky_crm', 'field_house_number')"
              :disabled="isLoading"
            />
            <NcTextField
              v-model="address.address_addition"
              :label="t('sticky_crm', 'field_address_addition')"
              :disabled="isLoading"
            />
            <NcTextField
              v-model="address.postal_code"
              :label="t('sticky_crm', 'field_postal_code')"
              :disabled="isLoading"
            />
            <NcTextField
              v-model="address.city"
              :label="t('sticky_crm', 'field_city')"
              :disabled="isLoading"
            />
            <NcTextField
              v-model="address.country_code"
              :label="t('sticky_crm', 'field_country_code')"
              :disabled="isLoading"
              maxlength="2"
            />
          </div>
        </div>

        <NcButton
          type="secondary"
          class="add-address-btn"
          :disabled="isLoading"
          @click="addBlankAddress"
        >
          <template #icon>
            <IconPlus :size="16" />
          </template>
          {{ t('sticky_crm', 'btn_add_address') }}
        </NcButton>
      </fieldset>

      <!-- TAX & REGISTER -->
      <fieldset class="sidebar-fieldset">
        <legend>{{ t('sticky_crm', 'section_tax') }}</legend>
        <NcTextField
          v-model="editableClient.vat_id"
          :label="t('sticky_crm', 'field_vat_id')"
          :disabled="isLoading"
        />
        <NcTextField
          v-model="editableClient.tax_number"
          :label="t('sticky_crm', 'field_tax_number')"
          :disabled="isLoading"
        />
        <NcTextField
          v-model="editableClient.register_court"
          :label="t('sticky_crm', 'field_register_court')"
          :disabled="isLoading"
        />
        <NcTextField
          v-model="editableClient.register_number"
          :label="t('sticky_crm', 'field_register_number')"
          :disabled="isLoading"
        />
      </fieldset>
    </div>

    <div class="sidebar-actions">
      <NcButton type="error" :disabled="isLoading" @click="handleDelete">
        <template #icon>
          <IconDelete :size="16" />
        </template>
        {{ t('sticky_crm', 'client_delete') }}
      </NcButton>

      <NcButton type="primary" :disabled="isLoading" @click="handleSave">
        <template #icon>
          <IconCheck :size="16" />
        </template>
        {{ isLoading ? t('sticky_crm', 'client_saving') : t('sticky_crm', 'client_save') }}
      </NcButton>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import { updateClient, deleteClient, DEFAULT_CRM_CONFIG } from '../services/clientService'

const props = defineProps({
	client: { type: Object, required: true },
	crmConfig: { type: Object, default: () => DEFAULT_CRM_CONFIG },
	availableUsers: { type: Array, default: () => [] },
})
const emit = defineEmits(['client-updated', 'client-deleted'])

const normalizeClientForEdit = (raw) => {
	const clone = JSON.parse(JSON.stringify(raw || {}))
	if (!Array.isArray(clone.addresses)) clone.addresses = []
	if (!Array.isArray(clone.categories)) clone.categories = []
	if (!Array.isArray(clone.emails) || clone.emails.length === 0) {
		clone.emails = clone.contact_email
			? [{ label: 'Primary', email: clone.contact_email }]
			: [{ label: 'Primary', email: '' }]
	}
	if (!Array.isArray(clone.phones) || clone.phones.length === 0) {
		clone.phones = clone.phone
			? [{ label: 'Primary', phone: clone.phone }]
			: [{ label: 'Mobile', phone: '' }]
	}
	if (!clone.custom_fields || typeof clone.custom_fields !== 'object' || Array.isArray(clone.custom_fields)) {
		clone.custom_fields = {}
	}
	if (!clone.tier) clone.tier = ''
	if (!clone.assigned_to) clone.assigned_to = ''
	if (!clone.status) clone.status = 'in_progress'
	if (!clone.type) clone.type = 'company'
	return clone
}

const editableClient = ref(normalizeClientForEdit(props.client))
const isLoading = ref(false)
const errorMessage = ref('')

watch(() => props.client, (newClient) => {
	editableClient.value = normalizeClientForEdit(newClient)
	errorMessage.value = ''
}, { deep: true })

const isCategorySelected = (catId) => {
	return editableClient.value.categories.includes(catId)
}

const toggleCategory = (catId) => {
	const idx = editableClient.value.categories.indexOf(catId)
	if (idx === -1) {
		editableClient.value.categories.push(catId)
	} else {
		editableClient.value.categories.splice(idx, 1)
	}
}

const addEmail = () => {
	editableClient.value.emails.push({ label: 'Email', email: '' })
}

const removeEmail = (idx) => {
	editableClient.value.emails.splice(idx, 1)
	if (editableClient.value.emails.length === 0) {
		editableClient.value.emails.push({ label: 'Primary', email: '' })
	}
}

const addPhone = () => {
	editableClient.value.phones.push({ label: 'Mobile', phone: '' })
}

const removePhone = (idx) => {
	editableClient.value.phones.splice(idx, 1)
	if (editableClient.value.phones.length === 0) {
		editableClient.value.phones.push({ label: 'Primary', phone: '' })
	}
}

const addBlankAddress = () => {
	editableClient.value.addresses.push({
		type: 'billing',
		label: t('sticky_crm', 'address_label_billing_default'),
		street: '',
		house_number: '',
		address_addition: '',
		postal_code: '',
		city: '',
		country_code: 'CZ',
	})
}

const removeAddress = (index) => {
	editableClient.value.addresses.splice(index, 1)
}

const handleSave = async () => {
	isLoading.value = true
	errorMessage.value = ''
	try {
		const cleanEmails = editableClient.value.emails.filter(e => e.email && e.email.trim())
		const cleanPhones = editableClient.value.phones.filter(p => p.phone && p.phone.trim())

		const payload = {
			...editableClient.value,
			emails: cleanEmails,
			phones: cleanPhones,
			contact_email: cleanEmails[0]?.email || '',
			phone: cleanPhones[0]?.phone || '',
			addresses: editableClient.value.addresses.filter(a => (a.street && a.street.trim()) || (a.city && a.city.trim())),
		}
		const updatedData = await updateClient(payload)
		emit('client-updated', updatedData)
	} catch (error) {
		errorMessage.value = error.message || t('sticky_crm', 'error_save')
	} finally {
		isLoading.value = false
	}
}

const handleDelete = async () => {
	const confirmed = confirm(
		t('sticky_crm', 'confirm_delete_client').replace('{name}', props.client.name)
	)
	if (!confirmed) return

	isLoading.value = true
	try {
		await deleteClient(props.client.uuid)
		emit('client-deleted', props.client.uuid)
	} catch (error) {
		errorMessage.value = error.message
		isLoading.value = false
	}
}
</script>

<style lang="scss" scoped>
.sidebar-content {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.sidebar-form {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.sidebar-actions {
  padding: 14px 20px;
  border-top: 1px solid var(--color-border-light);
  display: flex;
  justify-content: space-between;
  background-color: var(--color-main-background);
}

.sidebar-fieldset {
  border: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;

  legend {
    font-weight: 600;
    font-size: 11px;
    color: var(--color-text-maxcontrast);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.field-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-maxcontrast);
}

.nc-input,
.nc-select {
  width: 100%;
  min-height: 34px;
  padding: 4px 10px;
  border-radius: var(--border-radius);
  border: 1px solid var(--color-border-dark);
  background: var(--color-main-background);
  color: var(--color-main-text);
  box-sizing: border-box;
  font-size: 13px;
}

.checkbox-inline {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  cursor: pointer;
}

.category-chips-selector {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.cat-chip-btn {
  padding: 4px 10px;
  font-size: 12px;
  border-radius: var(--border-radius-pill);
  border: 1.5px solid var(--color-border-dark);
  background: transparent;
  color: var(--color-main-text);
  cursor: pointer;
  transition: all 0.15s ease;
  font-weight: 500;
}

.multi-contact-block {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.multi-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.link-add-btn {
  background: none;
  border: none;
  color: var(--color-primary-element);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  padding: 2px 4px;
}

.multi-item-row {
  display: flex;
  gap: 6px;
  align-items: center;

  .multi-label-input {
    width: 85px;
    flex-shrink: 0;
  }

  .multi-value-input {
    flex: 1;
  }

  .multi-remove-btn {
    width: 28px;
    height: 32px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    background: var(--color-background-hover);
    color: var(--color-text-maxcontrast);
    cursor: pointer;
    flex-shrink: 0;

    &:hover {
      color: var(--color-error);
    }
  }
}

.error-banner {
  margin: 12px 20px 0;
  color: var(--color-error);
  background-color: var(--color-error-background);
  padding: 10px 12px;
  border-radius: var(--border-radius);
  font-size: 13px;
}

.sidebar-address-item {
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  padding: 14px;
  background-color: var(--color-background-hover);
  margin-bottom: 12px;

  .address-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    border-bottom: 1px solid var(--color-border-light);
    padding-bottom: 6px;
  }
}

.address-fields-vertical {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.add-address-btn {
  width: 100%;
  justify-content: center;
  margin-top: 4px;
}
</style>