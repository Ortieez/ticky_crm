<template>
  <NcContent app-name="sticky">
    <NcAppNavigation>
      <template #list>
        <!-- MAIN VIEWS -->
        <NcAppNavigationItem
          :name="t('sticky_crm', 'nav_all_clients') + ` (${clients.length})`"
          :active="activeNav === 'all'"
          @click="selectNav('all')"
        >
          <template #icon>
            <IconAccountMultiple :size="20" />
          </template>
        </NcAppNavigationItem>

        <NcAppNavigationItem
          :name="t('sticky_crm', 'nav_my_clients') + ` (${myClientsCount})`"
          :active="activeNav === 'mine'"
          @click="selectNav('mine')"
        >
          <template #icon>
            <IconAccount :size="20" />
          </template>
        </NcAppNavigationItem>

        <NcAppNavigationItem
          :name="t('sticky_crm', 'nav_dashboard')"
          :active="activeNav === 'dashboard'"
          @click="selectNav('dashboard')"
        >
          <template #icon>
            <IconChartBoxOutline :size="20" />
          </template>
        </NcAppNavigationItem>

        <!-- STATUS QUICK FILTERS -->
        <li class="nav-section-header">
          {{ t('sticky_crm', 'field_status') }}
        </li>
        <NcAppNavigationItem
          v-for="st in crmConfig.statuses"
          :key="'nav-st-' + st.id"
          :name="`${st.label} (${countByStatus(st.id)})`"
          :active="activeNav === 'status:' + st.id"
          @click="selectNav('status:' + st.id)"
        >
          <template #icon>
            <span class="nav-color-dot" :style="{ backgroundColor: st.color }" />
          </template>
        </NcAppNavigationItem>

        <!-- PARTNERSHIP TIER QUICK FILTERS -->
        <li class="nav-section-header">
          {{ t('sticky_crm', 'field_tier') }}
        </li>
        <NcAppNavigationItem
          v-for="tr in crmConfig.tiers"
          :key="'nav-tr-' + tr.id"
          :name="`${tr.label} (${countByTier(tr.id)})`"
          :active="activeNav === 'tier:' + tr.id"
          @click="selectNav('tier:' + tr.id)"
        >
          <template #icon>
            <span class="nav-color-dot tier-star" :style="{ backgroundColor: tr.color }" />
          </template>
        </NcAppNavigationItem>

        <!-- CATEGORY QUICK FILTERS -->
        <li class="nav-section-header">
          {{ t('sticky_crm', 'field_categories') }}
        </li>
        <NcAppNavigationItem
          v-for="cat in crmConfig.categories"
          :key="'nav-cat-' + cat.id"
          :name="`${cat.label} (${countByCategory(cat.id)})`"
          :active="activeNav === 'cat:' + cat.id"
          @click="selectNav('cat:' + cat.id)"
        >
          <template #icon>
            <span class="nav-color-dot" :style="{ backgroundColor: cat.color }" />
          </template>
        </NcAppNavigationItem>
      </template>

      <template #footer>
        <div class="navigation__footer">
          <NcButton wide type="secondary" @click="showImportExportModal = true">
            Import / Export
          </NcButton>
          <NcButton wide @click="showSettingsModal = true">
            <template #icon>
              <IconCogOutline :size="20" />
            </template>
            {{ t('sticky_crm', 'nav_settings') }}
          </NcButton>

          <SettingsDialog
            v-if="showSettingsModal"
            @close="showSettingsModal = false"
            @settings-saved="handleSettingsSaved"
            @clients-updated="handleBulkClientsUpdated"
          />

          <ImportExportDialog
            v-if="showImportExportModal"
            :all-clients="clients"
            :filtered-clients="filteredClients"
            :crm-config="crmConfig"
            @close="showImportExportModal = false"
            @imported="handleBulkClientsUpdated"
          />
        </div>
      </template>
    </NcAppNavigation>

    <NcAppContent :page-heading="t('sticky_crm', 'page_heading_clients')">
      <StickyHeader
        :title="currentViewTitle"
        :subtitle="`${filteredClients.length} / ${clients.length} total`"
      >
        <template #actions>
          <NcButton type="primary" @click="isDialogOpen = true">
            <template #icon>
              <IconPlus :size="20" />
            </template>
            {{ t('sticky_crm', 'client_create_submit') }}
          </NcButton>
        </template>
      </StickyHeader>

      <!-- DASHBOARD / OVERVIEW VIEW -->
      <div v-if="activeNav === 'dashboard'" class="crm-dashboard">
        <div class="dashboard-grid">
          <div class="dash-card">
            <h3>By Sponsorship Status</h3>
            <div class="dash-stat-list">
              <div
                v-for="st in crmConfig.statuses"
                :key="st.id"
                class="dash-stat-row"
                @click="selectNav('status:' + st.id)"
              >
                <span class="dash-dot" :style="{ backgroundColor: st.color }" />
                <span class="dash-label">{{ st.label }}</span>
                <strong class="dash-count">{{ countByStatus(st.id) }}</strong>
              </div>
            </div>
          </div>

          <div class="dash-card">
            <h3>By Partnership Tier</h3>
            <div class="dash-stat-list">
              <div
                v-for="tr in crmConfig.tiers"
                :key="tr.id"
                class="dash-stat-row"
                @click="selectNav('tier:' + tr.id)"
              >
                <span class="dash-dot" :style="{ backgroundColor: tr.color }" />
                <span class="dash-label">{{ tr.label }}</span>
                <strong class="dash-count">{{ countByTier(tr.id) }}</strong>
              </div>
            </div>
          </div>

          <div class="dash-card">
            <h3>By Category</h3>
            <div class="dash-stat-list">
              <div
                v-for="cat in crmConfig.categories"
                :key="cat.id"
                class="dash-stat-row"
                @click="selectNav('cat:' + cat.id)"
              >
                <span class="dash-dot" :style="{ backgroundColor: cat.color }" />
                <span class="dash-label">{{ cat.label }}</span>
                <strong class="dash-count">{{ countByCategory(cat.id) }}</strong>
              </div>
            </div>
          </div>

          <div class="dash-card">
            <h3>By Assignee (Kdo to řeší)</h3>
            <div class="dash-stat-list">
              <div
                v-for="item in assigneeBreakdown"
                :key="item.name"
                class="dash-stat-row"
                @click="filterByAssigneeFromDashboard(item.raw)"
              >
                <span class="dash-label">{{ item.name }}</span>
                <strong class="dash-count">{{ item.count }}</strong>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TABLE VIEW WITH FILTER BAR -->
      <template v-else>
        <div class="crm-filter-bar">
          <div class="filter-search">
            <input
              v-model="filters.search"
              type="search"
              class="filter-input search-input"
              placeholder="Search name, email, phone, ID, assignee..."
            >
          </div>

          <div class="filter-item">
            <select v-model="filters.status" class="filter-select">
              <option value="">All Statuses</option>
              <option v-for="st in crmConfig.statuses" :key="st.id" :value="st.id">
                Status: {{ st.label }}
              </option>
            </select>
          </div>

          <div class="filter-item">
            <select v-model="filters.tier" class="filter-select">
              <option value="">All Tiers</option>
              <option v-for="tr in crmConfig.tiers" :key="tr.id" :value="tr.id">
                Tier: {{ tr.label }}
              </option>
              <option value="__none__">Tier: Unassigned</option>
            </select>
          </div>

          <div class="filter-item">
            <select v-model="filters.category" class="filter-select">
              <option value="">All Categories</option>
              <option v-for="cat in crmConfig.categories" :key="cat.id" :value="cat.id">
                Category: {{ cat.label }}
              </option>
            </select>
          </div>

          <div class="filter-item">
            <select v-model="filters.assignee" class="filter-select">
              <option value="">All Assignees</option>
              <option v-for="ass in uniqueAssignees" :key="ass" :value="ass">
                Assignee: {{ ass }}
              </option>
              <option value="__unassigned__">Unassigned</option>
            </select>
          </div>

          <button
            v-if="hasActiveFilters"
            type="button"
            class="reset-filters-btn"
            @click="resetFilters"
          >
            ✕ Reset Filters
          </button>
        </div>

        <StickyTable
          :value="filteredClients"
          :columns-config="crmConfig.columns"
          :crm-config="crmConfig"
          @row-click="selectedClient = $event"
        />
      </template>

      <NewClientDialog
        v-model:open="isDialogOpen"
        :crm-config="crmConfig"
        :available-users="allUsers"
        :default-assignee="currentUser?.label || currentUser?.id || ''"
        @client-created="handleClientCreated"
      />
    </NcAppContent>

    <NcAppSidebar
      v-if="selectedClient"
      :title="selectedClient.name"
      :subtitle="selectedClient.assigned_to ? `${selectedClient.client_number} • ${selectedClient.assigned_to}` : selectedClient.client_number"
      :active="activeSidebarTab"
      @update:active="activeSidebarTab = $event"
      @close="selectedClient = null"
    >
      <NcAppSidebarTab id="sticky-details" :name="t('sticky_crm', 'tab_details')" :tab-index="0">
        <template #icon>
          <IconAccount :size="20" />
        </template>
        <ClientTab
          :client="selectedClient"
          :crm-config="crmConfig"
          :available-users="allUsers"
          @client-updated="handleClientUpdated"
          @client-deleted="handleClientDeleted"
        />
      </NcAppSidebarTab>

      <NcAppSidebarTab id="sticky-notes" :name="t('sticky_crm', 'tab_notes')" :tab-index="1">
        <template #icon>
          <IconNoteText :size="20" />
        </template>
        <ClientNotesTab :client-id="selectedClient.id" />
      </NcAppSidebarTab>

      <NcAppSidebarTab id="contacts" name="Contacts" icon="icon-contacts-dark">
        <ContactTab :client-id="selectedClient.uuid" />
      </NcAppSidebarTab>

      <NcAppSidebarTab id="sticky-activities" :name="t('sticky_crm', 'tab_activities')" :tab-index="2">
        <template #icon>
          <svg
            id="mdi-lightning-bolt"
            xmlns="http://www.w3.org/2000/svg"
            height="20px"
            viewBox="0 0 24 24"
          ><path d="M11 15H6L13 1V9H18L11 23V15Z" /></svg>
        </template>
        <ActivityTab :client-uuid="selectedClient.uuid" />
      </NcAppSidebarTab>
    </NcAppSidebar>
  </NcContent>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconAccount from 'vue-material-design-icons/Account.vue'
import IconAccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import IconNoteText from 'vue-material-design-icons/NoteTextOutline.vue'
import IconCogOutline from 'vue-material-design-icons/CogOutline.vue'
import IconChartBoxOutline from 'vue-material-design-icons/ChartBoxOutline.vue'
import StickyTable from './components/StickyTable.vue'
import StickyHeader from './components/StickyHeader.vue'
import NewClientDialog from './dialogs/NewClientDialog.vue'
import ActivityTab from './components/ActivityTab.vue'
import ClientTab from './components/ClientTab.vue'
import ClientNotesTab from './components/ClientNotesTab.vue'
import ContactTab from './components/ContactTab.vue'
import SettingsDialog from './dialogs/SettingsDialog.vue'
import ImportExportDialog from './dialogs/ImportExportDialog.vue'
import { getClients, getSettingsData, DEFAULT_CRM_CONFIG } from './services/clientService'

const isDialogOpen = ref(false)
const showSettingsModal = ref(false)
const showImportExportModal = ref(false)

const clients = ref([])
const selectedClient = ref(null)
const activeSidebarTab = ref('sticky-details')
const activeNav = ref('all')

const crmConfig = reactive(JSON.parse(JSON.stringify(DEFAULT_CRM_CONFIG)))
const allUsers = ref([])
const currentUser = ref(null)

const filters = reactive({
	search: '',
	status: '',
	tier: '',
	category: '',
	assignee: '',
})

const isAssignedToCurrentUser = (client) => {
	if (!client.assigned_to || !currentUser.value) return false
	const val = client.assigned_to.toLowerCase().trim()
	const uid = (currentUser.value.id || '').toLowerCase().trim()
	const label = (currentUser.value.label || '').toLowerCase().trim()
	return val === uid || val === label || (uid && val.includes(uid)) || (label && val.includes(label))
}

const myClientsCount = computed(() => {
	return clients.value.filter(isAssignedToCurrentUser).length
})

const countByStatus = (statusId) => {
	return clients.value.filter(c => c.status === statusId).length
}

const countByTier = (tierId) => {
	return clients.value.filter(c => c.tier === tierId).length
}

const countByCategory = (catId) => {
	return clients.value.filter(c => Array.isArray(c.categories) && c.categories.includes(catId)).length
}

const uniqueAssignees = computed(() => {
	const set = new Set()
	clients.value.forEach(c => {
		if (c.assigned_to && c.assigned_to.trim()) {
			set.add(c.assigned_to.trim())
		}
	})
	allUsers.value.forEach(u => {
		if (u.label) set.add(u.label)
	})
	return Array.from(set).sort((a, b) => a.localeCompare(b))
})

const assigneeBreakdown = computed(() => {
	const counts = new Map()
	let unassigned = 0
	clients.value.forEach(c => {
		const a = c.assigned_to ? c.assigned_to.trim() : ''
		if (!a) {
			unassigned++
		} else {
			counts.set(a, (counts.get(a) || 0) + 1)
		}
	})
	const result = Array.from(counts.entries())
		.map(([name, count]) => ({ name, raw: name, count }))
		.sort((a, b) => b.count - a.count)
	if (unassigned > 0) {
		result.push({ name: 'Unassigned', raw: '__unassigned__', count: unassigned })
	}
	return result
})

const selectNav = (navId) => {
	activeNav.value = navId
	if (navId === 'all' || navId === 'mine' || navId === 'dashboard') {
		filters.status = ''
		filters.tier = ''
		filters.category = ''
	} else if (navId.startsWith('status:')) {
		filters.status = navId.slice('status:'.length)
		filters.tier = ''
		filters.category = ''
	} else if (navId.startsWith('tier:')) {
		filters.tier = navId.slice('tier:'.length)
		filters.status = ''
		filters.category = ''
	} else if (navId.startsWith('cat:')) {
		filters.category = navId.slice('cat:'.length)
		filters.status = ''
		filters.tier = ''
	}
}

const filterByAssigneeFromDashboard = (rawAssignee) => {
	activeNav.value = 'all'
	filters.assignee = rawAssignee
}

const hasActiveFilters = computed(() => {
	return Boolean(
		filters.search.trim()
		|| filters.status
		|| filters.tier
		|| filters.category
		|| filters.assignee
	)
})

const resetFilters = () => {
	filters.search = ''
	filters.status = ''
	filters.tier = ''
	filters.category = ''
	filters.assignee = ''
	if (activeNav.value.startsWith('status:') || activeNav.value.startsWith('tier:') || activeNav.value.startsWith('cat:')) {
		activeNav.value = 'all'
	}
}

const currentViewTitle = computed(() => {
	if (activeNav.value === 'dashboard') return 'Sponsorship Overview'
	if (activeNav.value === 'mine') return t('sticky_crm', 'nav_my_clients')
	if (activeNav.value.startsWith('status:')) {
		const id = activeNav.value.slice('status:'.length)
		const st = crmConfig.statuses.find(s => s.id === id)
		return st ? `Status: ${st.label}` : t('sticky_crm', 'app_title')
	}
	if (activeNav.value.startsWith('tier:')) {
		const id = activeNav.value.slice('tier:'.length)
		const tr = crmConfig.tiers.find(t => t.id === id)
		return tr ? `Tier: ${tr.label}` : t('sticky_crm', 'app_title')
	}
	if (activeNav.value.startsWith('cat:')) {
		const id = activeNav.value.slice('cat:'.length)
		const cat = crmConfig.categories.find(c => c.id === id)
		return cat ? `Category: ${cat.label}` : t('sticky_crm', 'app_title')
	}
	return t('sticky_crm', 'app_title')
})

const filteredClients = computed(() => {
	return clients.value.filter(client => {
		if (activeNav.value === 'mine' && !isAssignedToCurrentUser(client)) {
			return false
		}

		if (filters.status && client.status !== filters.status) {
			return false
		}

		if (filters.tier) {
			if (filters.tier === '__none__') {
				if (client.tier) return false
			} else if (client.tier !== filters.tier) {
				return false
			}
		}

		if (filters.category) {
			if (!Array.isArray(client.categories) || !client.categories.includes(filters.category)) {
				return false
			}
		}

		if (filters.assignee) {
			if (filters.assignee === '__unassigned__') {
				if (client.assigned_to && client.assigned_to.trim()) return false
			} else if ((client.assigned_to || '').trim() !== filters.assignee) {
				return false
			}
		}

		const q = filters.search.trim().toLowerCase()
		if (q) {
			const emailsText = Array.isArray(client.emails)
				? client.emails.map(e => e.email).join(' ')
				: (client.contact_email || '')
			const phonesText = Array.isArray(client.phones)
				? client.phones.map(p => p.phone).join(' ')
				: (client.phone || '')
			const customText = client.custom_fields
				? Object.values(client.custom_fields).join(' ')
				: ''
			const haystack = [
				client.name,
				client.client_number,
				client.assigned_to,
				client.tier,
				client.status,
				emailsText,
				phonesText,
				customText,
				...(client.categories || []),
			].filter(Boolean).join(' ').toLowerCase()

			if (!haystack.includes(q)) {
				return false
			}
		}

		return true
	})
})

const handleSettingsSaved = (newConfig) => {
	if (newConfig) {
		Object.assign(crmConfig, JSON.parse(JSON.stringify(newConfig)))
	}
}

const handleBulkClientsUpdated = (updatedClientsList) => {
	if (Array.isArray(updatedClientsList)) {
		clients.value = updatedClientsList
	}
}

const handleClientCreated = (newClient) => {
	clients.value.push(newClient)
}

const handleClientUpdated = (updatedClient) => {
	const index = clients.value.findIndex(c => c.id === updatedClient.id)
	if (index !== -1) clients.value[index] = updatedClient
	selectedClient.value = updatedClient
}

const handleClientDeleted = (deletedClientId) => {
	clients.value = clients.value.filter(c => c.uuid !== deletedClientId)
	selectedClient.value = null
}

const loadInitialData = async () => {
	try {
		const [clientsList, settingsRes] = await Promise.all([
			getClients(),
			getSettingsData().catch(() => null),
		])
		clients.value = clientsList
		if (settingsRes) {
			if (settingsRes.crm_config) {
				Object.assign(crmConfig, settingsRes.crm_config)
			}
			if (settingsRes.all_users) {
				allUsers.value = Object.values(settingsRes.all_users)
			}
			if (settingsRes.current_user) {
				currentUser.value = settingsRes.current_user
			}
		}
	} catch (error) {
		console.error(error)
	}
}

onMounted(() => {
	loadInitialData().then(async () => {
		const params = new URLSearchParams(window.location.search)
		const clientId = params.get('client')
		const tab = params.get('tab')

		if (clientId) {
			const client = clients.value.find(c => c.id === parseInt(clientId))
			if (client) {
				selectedClient.value = client
				await nextTick()
				if (tab === 'notes') activeSidebarTab.value = 'sticky-notes'
			}
		}
	})
})
</script>

<style lang="scss" scoped>
.navigation__footer {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px;
  width: 100%;
  box-sizing: border-box;
}

.nav-section-header {
  padding: 14px 16px 4px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: var(--color-text-maxcontrast);
  list-style: none;
}

.nav-color-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  margin-left: 5px;
}

.legacy-import-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 24px;
  background-color: var(--color-background-hover);
  border-left: 4px solid var(--color-success);
  border-bottom: 1px solid var(--color-border-light);
  font-size: 13px;
  font-weight: 500;
  color: var(--color-main-text);

  &.error {
    border-left-color: var(--color-error);
    color: var(--color-error);
  }

  .banner-close-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    color: var(--color-text-maxcontrast);
  }
}

.crm-filter-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  padding: 12px 24px;
  background-color: var(--color-background-hover);
  border-bottom: 1px solid var(--color-border-light);

  .filter-search {
    flex: 1 1 240px;
    min-width: 200px;
  }

  .filter-input,
  .filter-select {
    height: 34px;
    padding: 0 10px;
    border-radius: var(--border-radius);
    border: 1px solid var(--color-border-dark);
    background-color: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 13px;
    width: 100%;
    box-sizing: border-box;
  }

  .reset-filters-btn {
    height: 34px;
    padding: 0 12px;
    border-radius: var(--border-radius);
    border: 1px solid var(--color-border-dark);
    background: var(--color-main-background);
    color: var(--color-error);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
  }
}

.crm-dashboard {
  padding: 24px;

  .dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
  }

  .dash-card {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    padding: 18px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);

    h3 {
      margin: 0 0 12px;
      font-size: 14px;
      font-weight: 600;
      color: var(--color-text-maxcontrast);
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }
  }

  .dash-stat-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .dash-stat-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: background-color 0.12s ease;

    &:hover {
      background: var(--color-background-hover);
    }

    .dash-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .dash-label {
      flex: 1;
      font-size: 13px;
    }

    .dash-count {
      font-size: 14px;
      font-weight: 700;
    }
  }
}
</style>