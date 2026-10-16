import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const DEFAULT_CRM_CONFIG = {
	row_coloring_enabled: true,
	tiers: [
		{ id: 'general', label: 'General', color: '#8b5cf6' },
		{ id: 'gold', label: 'Gold', color: '#eab308' },
		{ id: 'silver', label: 'Silver', color: '#64748b' },
		{ id: 'bronze', label: 'Bronze', color: '#d97706' },
		{ id: 'starter', label: 'Starter', color: '#3b82f6' },
	],
	statuses: [
		{ id: 'lead', label: 'Lead / To Contact', color: '#6b7280' },
		{ id: 'in_progress', label: 'In Progress (V řešení)', color: '#3b82f6' },
		{ id: 'confirmed', label: 'Confirmed (Podpoří)', color: '#10b981' },
		{ id: 'rejected', label: 'Rejected (Nepodpoří)', color: '#ef4444' },
		{ id: 'active', label: 'Active', color: '#059669' },
		{ id: 'inactive', label: 'Inactive', color: '#9ca3af' },
	],
	categories: [
		{ id: 'obrabeni', label: 'Obrábění', color: '#0ea5e9' },
		{ id: 'penize', label: 'Peníze', color: '#10b981' },
		{ id: 'slevy', label: 'Slevy', color: '#f59e0b' },
		{ id: 'material', label: 'Materiál', color: '#8b5cf6' },
		{ id: 'software', label: 'Software', color: '#6366f1' },
		{ id: 'logistika', label: 'Logistika', color: '#ec4899' },
	],
	columns: [
		{ field: 'name', label: 'Name', visible: true, isCustom: false, type: 'text' },
		{ field: 'assigned_to', label: 'Assignee', visible: true, isCustom: false, type: 'user' },
		{ field: 'status', label: 'Status', visible: true, isCustom: false, type: 'status' },
		{ field: 'tier', label: 'Tier', visible: true, isCustom: false, type: 'tier' },
		{ field: 'categories', label: 'Categories', visible: true, isCustom: false, type: 'categories' },
		{ field: 'emails', label: 'Email', visible: true, isCustom: false, type: 'emails' },
		{ field: 'phones', label: 'Phone', visible: true, isCustom: false, type: 'phones' },
		{ field: 'client_number', label: 'Client ID', visible: true, isCustom: false, type: 'text' },
		{ field: 'website', label: 'Website', visible: false, isCustom: false, type: 'url' },
		{ field: 'vat_id', label: 'VAT ID', visible: false, isCustom: false, type: 'text' },
	],
	custom_columns: [],
}

export const getClients = async () => {
	const url = generateUrl('/apps/sticky_crm/api/v1/clients')
	try {
		const response = await axios.get(url)
		return response.data
	} catch (error) {
		console.error('Error loading clients:', error.response?.data || error.message)
		throw new Error(error.response?.data?.message || 'Could not load client list.')
	}
}

export const createClient = async (clientData) => {
	const url = generateUrl('/apps/sticky_crm/api/v1/clients')
	const response = await axios.post(url, clientData)
	return response.data
}

export const updateClient = async (client) => {
	try {
		const response = await axios.put(
			generateUrl(`/apps/sticky_crm/api/v1/clients/${client.uuid}`),
			client
		)
		return response.data
	} catch (error) {
		throw new Error(
			error.response?.data?.message || 'Client could not be updated.'
		)
	}
}

export const deleteClient = async (uuid) => {
	try {
		await axios.delete(generateUrl(`/apps/sticky_crm/api/v1/clients/${uuid}`))
		return true
	} catch (error) {
		throw new Error(error.response?.data?.message || 'Error deleting client.')
	}
}

export const importClients = async (records) => {
	const url = generateUrl('/apps/sticky_crm/api/v1/clients')
	const response = await axios.post(url, { bulk_records: records })
	return response.data
}

export const extractAssigneesFromClientNumbers = async (overwrite = false) => {
	const url = generateUrl('/apps/sticky_crm/api/v1/clients')
	const response = await axios.post(url, { extract_assignees: true, overwrite })
	return response.data
}

export const fetchLegacyTickyClients = async () => {
	// 1. Try reading from GET /apps/sticky_crm/api/v1/settings (existing route, queries ticky_clients table)
	try {
		const settingsUrl = generateUrl('/apps/sticky_crm/api/v1/settings')
		const res = await axios.get(settingsUrl)
		if (Array.isArray(res.data?.legacy_ticky_clients) && res.data.legacy_ticky_clients.length > 0) {
			return res.data.legacy_ticky_clients
		}
	} catch (e) {
		console.warn('Could not read legacy_ticky_clients from settings:', e)
	}

	// 2. Fallback: Try reading from original Ticky CRM API GET /apps/ticky_crm/api/v1/clients
	try {
		const legacyApiUrl = generateUrl('/apps/ticky_crm/api/v1/clients')
		const res = await axios.get(legacyApiUrl)
		if (Array.isArray(res.data)) {
			return res.data
		}
	} catch (e) {
		console.warn('Could not read from /apps/ticky_crm/api/v1/clients:', e)
	}

	return []
}

export const getSettingsData = async () => {
	const url = generateUrl('/apps/sticky_crm/api/v1/settings')
	const response = await axios.get(url)
	return response.data
}

export const saveSettingsData = async (payload) => {
	const url = generateUrl('/apps/sticky_crm/api/v1/settings')
	const response = await axios.post(url, payload)
	return response.data
}