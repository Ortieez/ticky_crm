<template>
  <NcDialog
    v-model:open="showDialog"
    :name="t('sticky_crm', 'client_create_title')"
    size="large"
    :buttons="buttons"
    :close-on-click-outside="!isLoading"
  >
    <div class="sticky-form-container">
      <div v-if="errorMessage" class="sticky-error-banner">
        {{ errorMessage }}
      </div>

      <fieldset class="sticky-fieldset">
        <legend>{{ t('sticky_crm', 'section_master_data') }}</legend>
        <div class="sticky-grid">
          <NcTextField
            v-model="newClient.name"
            :label="t('sticky_crm', 'field_name')"
            :disabled="isLoading"
            required
          />

          <div class="field-group">
            <label class="field-label">{{ t('sticky_crm', 'field_assigned_to') }}</label>
            <input
              v-model="newClient.assigned_to"
              type="text"
              list="sticky-new-users-datalist"
              class="nc-input"
              :placeholder="t('sticky_crm', 'placeholder_assigned_to')"
              :disabled="isLoading"
            >
            <datalist id="sticky-new-users-datalist">
              <option v-for="u in availableUsers" :key="u.id" :value="u.label">
                {{ u.id }}
              </option>
            </datalist>
          </div>

          <div class="field-group">
            <label class="field-label">{{ t('sticky_crm', 'field_status') }}</label>
            <select v-model="newClient.status" class="nc-select" :disabled="isLoading">
              <option v-for="st in crmConfig.statuses" :key="st.id" :value="st.id">
                {{ st.label }}
              </option>
            </select>
          </div>

          <div class="field-group">
            <label class="field-label">{{ t('sticky_crm', 'field_tier') }}</label>
            <select v-model="newClient.tier" class="nc-select" :disabled="isLoading">
              <option value="">— None —</option>
              <option v-for="tr in crmConfig.tiers" :key="tr.id" :value="tr.id">
                {{ tr.label }}
              </option>
            </select>
          </div>

          <div class="field-group sticky-grid-full-width">
            <label class="field-label">{{ t('sticky_crm', 'field_categories') }}</label>
            <div class="category-chips-selector">
              <button
                v-for="cat in crmConfig.categories"
                :key="cat.id"
                type="button"
                class="cat-chip-btn"
                :class="{ selected: newClient.categories.includes(cat.id) }"
                :style="newClient.categories.includes(cat.id) ? { backgroundColor: cat.color, borderColor: cat.color, color: '#fff' } : { borderColor: cat.color }"
                :disabled="isLoading"
                @click="toggleCategory(cat.id)"
              >
                {{ cat.label }}
              </button>
            </div>
          </div>

          <NcTextField
            v-model="newClient.client_number"
            :label="t('sticky_crm', 'field_client_number') + ' (optional)'"
            :disabled="isLoading"
          />

          <div class="field-group">
            <label class="field-label">{{ t('sticky_crm', 'field_type') }}</label>
            <select v-model="newClient.type" class="nc-select" :disabled="isLoading">
              <option value="company">{{ t('sticky_crm', 'type_company') }}</option>
              <option value="person">{{ t('sticky_crm', 'type_person') }}</option>
            </select>
          </div>
        </div>
      </fieldset>

      <fieldset class="sticky-fieldset">
        <legend>{{ t('sticky_crm', 'section_contact') }}</legend>
        <div class="sticky-grid">
          <div class="multi-contact-block">
            <div class="multi-header">
              <label class="field-label">{{ t('sticky_crm', 'column_email') }}</label>
              <button type="button" class="link-add-btn" :disabled="isLoading" @click="addEmail">
                + {{ t('sticky_crm', 'btn_add_email') }}
              </button>
            </div>
            <div v-for="(item, idx) in newClient.emails" :key="'em-' + idx" class="multi-item-row">
              <input v-model="item.label" type="text" class="nc-input multi-label-input" placeholder="Label">
              <input v-model="item.email" type="email" class="nc-input multi-value-input" placeholder="email@example.com">
              <button type="button" class="multi-remove-btn" @click="removeEmail(idx)">
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
            <div v-for="(item, idx) in newClient.phones" :key="'ph-' + idx" class="multi-item-row">
              <input v-model="item.label" type="text" class="nc-input multi-label-input" placeholder="Label">
              <input v-model="item.phone" type="tel" class="nc-input multi-value-input" placeholder="+420 ...">
              <button type="button" class="multi-remove-btn" @click="removePhone(idx)">
                ✕
              </button>
            </div>
          </div>

          <NcTextField
            v-model="newClient.website"
            :label="t('sticky_crm', 'field_website')"
            type="url"
            :disabled="isLoading"
            class="sticky-grid-full-width"
          />
        </div>
      </fieldset>

      <fieldset v-if="crmConfig.custom_columns && crmConfig.custom_columns.length > 0" class="sticky-fieldset">
        <legend>{{ t('sticky_crm', 'section_custom_fields') }}</legend>
        <div class="sticky-grid">
          <div
            v-for="col in crmConfig.custom_columns"
            :key="col.id"
            class="field-group"
          >
            <template v-if="col.type === 'checkbox'">
              <label class="checkbox-inline">
                <input v-model="newClient.custom_fields[col.id]" type="checkbox" :disabled="isLoading">
                <span>{{ col.label }}</span>
              </label>
            </template>
            <template v-else-if="col.type === 'select'">
              <label class="field-label">{{ col.label }}</label>
              <select v-model="newClient.custom_fields[col.id]" class="nc-select" :disabled="isLoading">
                <option value="">—</option>
                <option v-for="opt in (col.options || [])" :key="opt" :value="opt">
                  {{ opt }}
                </option>
              </select>
            </template>
            <template v-else>
              <label class="field-label">{{ col.label }}</label>
              <input
                v-model="newClient.custom_fields[col.id]"
                :type="col.type === 'number' ? 'number' : (col.type === 'date' ? 'date' : (col.type === 'url' ? 'url' : 'text'))"
                class="nc-input"
                :disabled="isLoading"
              >
            </template>
          </div>
        </div>
      </fieldset>

      <fieldset class="sticky-fieldset">
        <legend>{{ t('sticky_crm', 'section_address') }}</legend>
        <div class="sticky-grid sticky-address-grid">
          <NcTextField
            v-model="newClient.address.street"
            :label="t('sticky_crm', 'field_street')"
            :disabled="isLoading"
          />

          <NcTextField
            v-model="newClient.address.house_number"
            :label="t('sticky_crm', 'field_house_number')"
            :disabled="isLoading"
          />

          <div class="sticky-grid-full-width address-row-inline">
            <NcTextField
              v-model="newClient.address.postal_code"
              :label="t('sticky_crm', 'field_postal_code')"
              :disabled="isLoading"
              class="field-plz"
            />

            <NcTextField
              v-model="newClient.address.city"
              :label="t('sticky_crm', 'field_city')"
              :disabled="isLoading"
              class="field-city"
            />

            <NcTextField
              v-model="newClient.address.country_code"
              :label="t('sticky_crm', 'field_country_code')"
              :disabled="isLoading"
              class="field-country"
              maxlength="2"
            />
          </div>

          <NcTextField
            v-model="newClient.address.address_addition"
            :label="t('sticky_crm', 'field_address_addition')"
            :disabled="isLoading"
            class="sticky-grid-full-width"
          />
        </div>
      </fieldset>

      <fieldset class="sticky-fieldset">
        <legend>{{ t('sticky_crm', 'section_tax') }}</legend>
        <div class="sticky-grid">
          <NcTextField
            v-model="newClient.vat_id"
            :label="t('sticky_crm', 'field_vat_id')"
            :disabled="isLoading"
          />

          <NcTextField
            v-model="newClient.tax_number"
            :label="t('sticky_crm', 'field_tax_number')"
            :disabled="isLoading"
          />

          <NcTextField
            v-model="newClient.register_court"
            :label="t('sticky_crm', 'field_register_court')"
            :disabled="isLoading"
          />

          <NcTextField
            v-model="newClient.register_number"
            :label="t('sticky_crm', 'field_register_number')"
            :disabled="isLoading"
          />
        </div>
      </fieldset>
    </div>
  </NcDialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { NcDialog, NcTextField } from '@nextcloud/vue'
import { createClient, DEFAULT_CRM_CONFIG } from '../services/clientService'

const props = defineProps({
	open: { type: Boolean, required: true },
	crmConfig: { type: Object, default: () => DEFAULT_CRM_CONFIG },
	availableUsers: { type: Array, default: () => [] },
	defaultAssignee: { type: String, default: '' },
})
const emit = defineEmits(['update:open', 'client-created'])

const showDialog = computed({
	get: () => props.open,
	set: (value) => emit('update:open', value),
})

const isLoading = ref(false)
const errorMessage = ref('')

const initialFormState = () => ({
	client_number: '',
	name: '',
	type: 'company',
	status: 'in_progress',
	assigned_to: props.defaultAssignee || '',
	tier: '',
	categories: [],
	emails: [{ label: 'Primary', email: '' }],
	phones: [{ label: 'Mobile', phone: '' }],
	custom_fields: {},
	website: '',
	vat_id: '',
	tax_number: '',
	register_court: '',
	register_number: '',
	address: {
		type: 'address',
		label: '',
		street: '',
		house_number: '',
		address_addition: '',
		postal_code: '',
		city: '',
		country_code: 'CZ',
	},
})

const newClient = ref(initialFormState())

watch(showDialog, (isOpen) => {
	if (!isOpen) {
		newClient.value = initialFormState()
		errorMessage.value = ''
	} else if (!newClient.value.assigned_to && props.defaultAssignee) {
		newClient.value.assigned_to = props.defaultAssignee
	}
})

const toggleCategory = (catId) => {
	const idx = newClient.value.categories.indexOf(catId)
	if (idx === -1) {
		newClient.value.categories.push(catId)
	} else {
		newClient.value.categories.splice(idx, 1)
	}
}

const addEmail = () => {
	newClient.value.emails.push({ label: 'Email', email: '' })
}

const removeEmail = (idx) => {
	newClient.value.emails.splice(idx, 1)
	if (newClient.value.emails.length === 0) {
		newClient.value.emails.push({ label: 'Primary', email: '' })
	}
}

const addPhone = () => {
	newClient.value.phones.push({ label: 'Mobile', phone: '' })
}

const removePhone = (idx) => {
	newClient.value.phones.splice(idx, 1)
	if (newClient.value.phones.length === 0) {
		newClient.value.phones.push({ label: 'Mobile', phone: '' })
	}
}

const handleSubmit = async () => {
	if (!newClient.value.name.trim()) {
		errorMessage.value = t('sticky_crm', 'error_required_fields')
		return
	}

	isLoading.value = true
	errorMessage.value = ''

	try {
		const { address, ...clientData } = newClient.value
		const cleanEmails = clientData.emails.filter(e => e.email && e.email.trim())
		const cleanPhones = clientData.phones.filter(p => p.phone && p.phone.trim())

		const payload = {
			...clientData,
			emails: cleanEmails,
			phones: cleanPhones,
			contact_email: cleanEmails[0]?.email || '',
			phone: cleanPhones[0]?.phone || '',
			addresses: (address.street.trim() || address.city.trim()) ? [address] : [],
		}

		const createdClient = await createClient(payload)
		emit('client-created', createdClient)
		showDialog.value = false
	} catch (error) {
		errorMessage.value = error.message || t('sticky_crm', 'error_save')
	} finally {
		isLoading.value = false
	}
}

const buttons = computed(() => [
	{
		label: t('sticky_crm', 'client_cancel'),
		type: 'normal',
		callback: () => { showDialog.value = false },
		disabled: isLoading.value,
	},
	{
		label: isLoading.value ? t('sticky_crm', 'client_create_saving') : t('sticky_crm', 'client_create_submit'),
		type: 'primary',
		callback: handleSubmit,
		disabled: isLoading.value || !newClient.value.name.trim(),
	},
])
</script>

<style scoped>
.sticky-form-container {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: 8px 20px 16px;
  width: 760px;
  max-width: 100%;
  box-sizing: border-box;
  margin: 0 auto;
}

.sticky-error-banner {
  background-color: var(--color-error-background);
  color: var(--color-error);
  border-left: 4px solid var(--color-error);
  padding: 12px 16px;
  border-radius: var(--border-radius);
  font-size: 13px;
}

.sticky-fieldset {
  border: none;
  padding: 0;
  margin: 0;

  legend {
    font-weight: 600;
    font-size: 11px;
    color: var(--color-text-maxcontrast);
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 0;
  }
}

.sticky-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px 18px;
}

.sticky-address-grid {
  grid-template-columns: 3fr 1fr;
}

.sticky-grid-full-width {
  grid-column: span 2;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
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
  padding-top: 18px;
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
}

.multi-item-row {
  display: flex;
  gap: 6px;
  align-items: center;

  .multi-label-input {
    width: 80px;
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
  }
}

.address-row-inline {
  display: flex;
  gap: 12px;
  width: 100%;
  align-items: flex-start;

  .field-plz {
    flex: 0 0 25%;
  }

  .field-city {
    flex: 1;
  }

  .field-country {
    flex: 0 0 20%;
  }
}

@media (max-width: 600px) {
  .sticky-grid {
    grid-template-columns: 1fr;
  }
  .sticky-grid-full-width {
    grid-column: span 1;
  }
}
</style>