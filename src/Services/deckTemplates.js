import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export class DeckTemplatesService {

	static instance = null

	static getInstance() {
		if (this.instance) return this.instance
		this.instance = new DeckTemplatesService()
		return this.instance
	}

	headers() {
		return {
			Accept: 'application/json',
			'OCS-APIRequest': 'true',
			'Content-Type': 'application/json',
		}
	}

	unwrap(data) {
		return data?.ocs?.data || data
	}

	async list(boardId = null) {
		const url = generateUrl('/apps/deck/card-policy/templates')
		const params = {}
		if (boardId !== null && boardId !== undefined && Number(boardId) > 0) params.boardId = Number(boardId)
		const response = await axios.get(url, { params, headers: this.headers() })
		return this.unwrap(response.data) ?? []
	}

	async createFromBoard(boardId, name) {
		const url = generateUrl('/apps/deck/card-policy/templates/from-board')
		const response = await axios.post(url, { boardId: Number(boardId), name }, { headers: this.headers() })
		return this.unwrap(response.data)
	}

	async delete(templateId, boardId = null) {
		const url = generateUrl(`/apps/deck/card-policy/templates/${Number(templateId)}`)
		const params = {}
		if (boardId !== null && boardId !== undefined && Number(boardId) > 0) params.boardId = Number(boardId)
		const response = await axios.delete(url, { params, headers: this.headers() })
		return this.unwrap(response.data)
	}

	async get(templateId, boardId) {
		const url = generateUrl(`/apps/deck/card-policy/templates/${Number(templateId)}`)
		const response = await axios.get(url, { params: { boardId: Number(boardId) }, headers: this.headers() })
		return this.unwrap(response.data)
	}

}
