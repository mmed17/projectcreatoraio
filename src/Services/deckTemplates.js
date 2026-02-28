import axios from 'axios'
import { generateUrl } from '@nextcloud/router'

export class DeckTemplatesService {
	static instance = null

	static getInstance() {
		if (this.instance) return this.instance
		this.instance = new DeckTemplatesService()
		return this.instance
	}

	async list(boardId = null) {
		const url = generateUrl('/apps/projectcreatoraio/api/v1/deck-templates')
		const params = {}
		if (boardId !== null && boardId !== undefined && Number(boardId) > 0) {
			params.boardId = Number(boardId)
		}
		const response = await axios.get(url, {
			params,
			headers: {
				'OCS-APIRequest': 'true',
				'Content-Type': 'application/json',
			},
		})
		return response.data ?? []
	}

	async createFromBoard(boardId, name) {
		const url = generateUrl('/apps/projectcreatoraio/api/v1/deck-templates/from-board')
		const response = await axios.post(
			url,
			{ boardId: Number(boardId), name },
			{
				headers: {
					'OCS-APIRequest': 'true',
					'Content-Type': 'application/json',
				},
			},
		)
		return response.data
	}

	async delete(templateId, boardId = null) {
		const url = generateUrl(`/apps/projectcreatoraio/api/v1/deck-templates/${Number(templateId)}`)
		const params = {}
		if (boardId !== null && boardId !== undefined && Number(boardId) > 0) {
			params.boardId = Number(boardId)
		}
		const response = await axios.delete(url, {
			params,
			headers: {
				'OCS-APIRequest': 'true',
				'Content-Type': 'application/json',
			},
		})
		return response.data
	}

	async get(templateId, boardId) {
		const url = generateUrl(`/apps/projectcreatoraio/api/v1/deck-templates/${Number(templateId)}`)
		const response = await axios.get(url, {
			params: { boardId: Number(boardId) },
			headers: {
				'OCS-APIRequest': 'true',
				'Content-Type': 'application/json',
			},
		})
		return response.data
	}
}
