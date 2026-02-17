import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export class DeckService {

	static instance = null

	static getInstance() {
		if (this.instance) {
			return this.instance
		}
		this.instance = new DeckService()
		return this.instance
	}

	headers() {
		return {
			Accept: 'application/json',
			'OCS-APIRequest': 'true',
			'Content-Type': 'application/json',
		}
	}

	async getBoard(boardId) {
		// Use non-OCS endpoints to match the Deck web UI.
		const url = generateUrl(`/apps/deck/boards/${boardId}`)
		const response = await axios.get(url, { headers: this.headers() })
		return response.data
	}

	async getBoardPermissions(boardId) {
		const url = generateUrl(`/apps/deck/boards/${boardId}/permissions`)
		const response = await axios.get(url, { headers: this.headers() })
		return response.data
	}

	async listStacks(boardId) {
		const url = generateUrl(`/apps/deck/stacks/${boardId}`)
		const response = await axios.get(url, { headers: this.headers() })
		return response.data ?? []
	}

	async createCard(stackId, title, order = 999) {
		const url = generateUrl('/apps/deck/cards')
		const response = await axios.post(url, { title, stackId, type: 'plain', order }, { headers: this.headers() })
		return response.data
	}

	async reorderCard(cardId, stackId, order) {
		const url = generateUrl(`/apps/deck/cards/${cardId}/reorder`)
		const response = await axios.put(url, { stackId, order }, { headers: this.headers() })
		return response.data
	}

	async getTransitionPermissions(boardId) {
		const url = generateUrl(`/apps/deck/boards/${boardId}/transition-permissions`)
		const response = await axios.get(url, { headers: this.headers() })
		return response.data ?? []
	}

	async addTransitionPermission(boardId, data) {
		const url = generateUrl(`/apps/deck/boards/${boardId}/transition-permissions`)
		const response = await axios.post(url, data, { headers: this.headers() })
		return response.data
	}

	async deleteTransitionPermission(id) {
		const url = generateUrl(`/apps/deck/transition-permissions/${id}`)
		const response = await axios.delete(url, { headers: this.headers() })
		return response.data
	}

	async listRoleProfiles(organizationId) {
		const url = generateUrl(`/apps/deck/organizations/${organizationId}/role-profiles`)
		const response = await axios.get(url, { headers: this.headers() })
		return response.data ?? []
	}

	async getRoleProfile(profileId) {
		const url = generateUrl(`/apps/deck/role-profiles/${profileId}`)
		const response = await axios.get(url, { headers: this.headers() })
		return response.data ?? null
	}

	async createRoleProfile(organizationId, name, permissions = []) {
		const url = generateUrl(`/apps/deck/organizations/${organizationId}/role-profiles`)
		const response = await axios.post(url, { name, permissions }, { headers: this.headers() })
		return response.data
	}

	async createRoleProfileFromBoard(boardId, name, organizationId) {
		const url = generateUrl(`/apps/deck/boards/${boardId}/export-role-profile`)
		const response = await axios.post(url, { name, organizationId }, { headers: this.headers() })
		return response.data
	}

	async applyRoleProfile(boardId, profileId, clearExisting = false) {
		const url = generateUrl(`/apps/deck/boards/${boardId}/apply-role-profile/${profileId}`)
		const response = await axios.post(url, { clearExisting }, { headers: this.headers() })
		return response.data
	}

	async deleteRoleProfile(profileId) {
		const url = generateUrl(`/apps/deck/role-profiles/${profileId}`)
		const response = await axios.delete(url, { headers: this.headers() })
		return response.data
	}

}
