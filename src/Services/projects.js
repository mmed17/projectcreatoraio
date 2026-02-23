import { generateUrl } from "@nextcloud/router";
import { Project } from "../Models/project";
import axios from "axios";

export class ProjectsService {

    static instance = null;
    constructor() {}

    /**
     * 
     * @returns {ProjectsService}
     */
    static getInstance() {
        if(this.instance) {
            return this.instance;
        }

        this.instance = new ProjectsService();
        return this.instance;
    }

    /**
     * 
     * @param {Project} project 
     * @returns {any}
     */
    async create(project) {
        const url = generateUrl('/apps/projectcreatoraio/api/v1/projects');
        const response = await axios.post(url, project.toJson(), {
            headers: {
                'OCS-APIRequest': 'true',
                'Content-Type': 'application/json'
            }
        });

        return response.data;
    }

    /**
     * 
     * @returns {Promise<any[]>}
     */
    async list() {
        try {
            const url = generateUrl('/apps/projectcreatoraio/api/v1/projects/list')
            const response = await axios.get(url, {
                headers: {
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/json'
                }
            });

            return response.data ?? [];

        } catch (e) {
            console.error('Failed to fetch projects:', e);
            return [];
        }
    }

    /**
     *
     * @returns {Promise<{userId: string, isGlobalAdmin: boolean, organizationRole: string|null, organizationId: number|null}|null>}
     */
    async context() {
        const url = generateUrl('/apps/projectcreatoraio/api/v1/projects/context')
        const response = await axios.get(url, {
            headers: {
                'OCS-APIRequest': 'true',
                'Content-Type': 'application/json'
            }
        });

        return response.data ?? null;
    }

    /**
     *
     * @param {number} projectId
     * @returns {Promise<any|null>}
     */
    async get(projectId) {
        try {
            const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}`)
            const response = await axios.get(url, {
                headers: {
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/json'
                }
            });

            return response.data ?? null;
        } catch (e) {
            console.error('Failed to fetch project details:', e);
            return null;
        }
    }

    /**
     * Get Combi card-visibility questionnaire state for a project.
     *
     * @param {number} projectId
     * @returns {Promise<any|null>}
     */
    async getCardVisibility(projectId) {
        try {
            const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/card-visibility`)
            const response = await axios.get(url, {
                headers: {
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/json',
                },
            })

            return response.data ?? null
        } catch (e) {
            console.error('Failed to fetch card visibility config:', e)
            throw e
        }
    }

    /**
     * Update Combi card-visibility questionnaire answers for a project.
     *
     * @param {number} projectId
     * @param {{cv_object_ownership?: number|null, cv_trace_ownership?: number|null, cv_building_type?: number|null, cv_avp_location?: number|null}} payload
     * @returns {Promise<any|null>}
     */
    async updateCardVisibility(projectId, payload) {
        try {
            const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/card-visibility`)
            const response = await axios.put(url, payload, {
                headers: {
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/json',
                },
            })

            return response.data ?? null
        } catch (e) {
            console.error('Failed to update card visibility config:', e)
            throw e
        }
    }

    /**
     * List project members.
     *
     * @param {number} projectId
     * @returns {Promise<Array<{id:string,displayName:string,email:string,isOwner:boolean}>>}
     */
    async listMembers(projectId) {
        try {
            const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/members`)
            const response = await axios.get(url, {
                headers: {
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/json',
                },
            })

            return response?.data?.members ?? []
        } catch (e) {
            console.error('Failed to list project members:', e)
            return []
        }
    }

    /**
     * Add a member to a project.
     *
     * @param {number} projectId
     * @param {string} userId
     * @returns {Promise<{added:boolean,alreadyMember:boolean,member:object}|null>}
     */
    async addMember(projectId, userId) {
        const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/members`)
        const response = await axios.post(url, { userId }, {
            headers: {
                'OCS-APIRequest': 'true',
                'Content-Type': 'application/json',
            },
        })

        return response?.data ?? null
    }

    /**
     * Search users in the current organization (or specific org for global admins).
     *
     * @param {string} query
     * @param {number|null} organizationId
     * @returns {Promise<Array<{id:string,user:string,label:string,displayName:string,subname:string}>>}
     */
    async searchUsers(query, organizationId = null) {
        try {
            const url = generateUrl('/apps/projectcreatoraio/api/v1/users/search')
            const params = new URLSearchParams()
            params.append('search', query)
            if (organizationId !== null && Number.isFinite(Number(organizationId))) {
                params.append('organizationId', String(organizationId))
            }

            const response = await axios.get(`${url}?${params.toString()}`, {
                headers: {
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/json',
                },
            })

            return response?.data?.users ?? []
        } catch (e) {
            console.error('Failed to search organization users:', e)
            return []
        }
    }

    /**
     *
     * @param {number} projectId
     * @returns {Promise<{shared: any[], private: any[]}>}
     */
    async getFiles(projectId) {
        try {
            const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/files`)
            const response = await axios.get(url, {
                headers: {
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/json'
                }
            });

            // API may return either { shared, private } or { files: { shared, private } }
            const payload = response.data ?? null
            if (payload && typeof payload === 'object' && payload.files) {
                return payload.files
            }

            return payload ?? { shared: [], private: [] };
        } catch (e) {
            console.error('Failed to fetch project files:', e);
            return { shared: [], private: [] };
        }
    }

	/**
	 *
	 * @param {number} projectId
	 * @returns {Promise<{fileId:number,name:string,mimetype:string,size:number,mtime:number,path:string}|null>}
	 */
	async getWhiteboardInfo(projectId) {
		try {
			const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/whiteboard`)
			const response = await axios.get(url, {
				headers: {
					'OCS-APIRequest': 'true',
					'Content-Type': 'application/json',
				},
			})
			return response.data ?? null
		} catch (e) {
			console.error('Failed to fetch project whiteboard info:', e)
			return null
		}
	}

	/**
	 * Update a project (partial fields).
	 *
	 * @param {number} projectId
	 * @param {object} payload
	 * @returns {Promise<any|null>}
	 */
	async update(projectId, payload) {
		try {
			const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}`)
			const response = await axios.put(url, payload, {
				headers: {
					'OCS-APIRequest': 'true',
					'Content-Type': 'application/json',
				},
			})
			return response.data ?? null
		} catch (e) {
			console.error('Failed to update project:', e)
			throw e
		}
	}

	/**
	 * Update project notes.
	 *
	 * @param {number} projectId
	 * @param {{public_note?: string, private_note?: string}} payload
	 * @returns {Promise<{public_note: string, private_note: string, private_note_available: boolean}|null>}
	 */
	async updateNotes(projectId, payload) {
		try {
			const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/notes`)
			const response = await axios.put(url, payload, {
				headers: {
					'OCS-APIRequest': 'true',
					'Content-Type': 'application/json',
				},
			})
			return response.data ?? null
		} catch (e) {
			console.error('Failed to update project notes:', e)
			throw e
		}
	}

	/**
	 * List all notes for a project.
	 *
	 * @param {number} projectId
	 * @returns {Promise<{notes: {public: array, private: array, private_available: boolean}}|null>}
	 */
	async listNotes(projectId) {
		try {
			const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/notes/list`)
			const response = await axios.get(url, {
				headers: {
					'OCS-APIRequest': 'true',
					'Content-Type': 'application/json',
				},
			})
			return response.data ?? null
		} catch (e) {
			console.error('Failed to list project notes:', e)
			return null
		}
	}

	/**
	 * Get a single note.
	 *
	 * @param {number} projectId
	 * @param {number} noteId
	 * @returns {Promise<object|null>}
	 */
	async getNote(projectId, noteId) {
		try {
			const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/notes/${noteId}`)
			const response = await axios.get(url, {
				headers: {
					'OCS-APIRequest': 'true',
					'Content-Type': 'application/json',
				},
			})
			return response.data ?? null
		} catch (e) {
			console.error('Failed to get note:', e)
			return null
		}
	}

	/**
	 * Create a new note.
	 *
	 * @param {number} projectId
	 * @param {{title: string, content: string, visibility: 'public'|'private'}} payload
	 * @returns {Promise<object|null>}
	 */
	async createNote(projectId, payload) {
		try {
			const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/notes`)
			const response = await axios.post(url, payload, {
				headers: {
					'OCS-APIRequest': 'true',
					'Content-Type': 'application/json',
				},
			})
			return response.data ?? null
		} catch (e) {
			console.error('Failed to create note:', e)
			throw e
		}
	}

	/**
	 * Update a note.
	 *
	 * @param {number} projectId
	 * @param {number} noteId
	 * @param {{title?: string, content?: string}} payload
	 * @returns {Promise<object|null>}
	 */
	async updateNote(projectId, noteId, payload) {
		try {
			const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/notes/${noteId}`)
			const response = await axios.put(url, payload, {
				headers: {
					'OCS-APIRequest': 'true',
					'Content-Type': 'application/json',
				},
			})
			return response.data ?? null
		} catch (e) {
			console.error('Failed to update note:', e)
			throw e
		}
	}

	/**
	 * Delete a note.
	 *
	 * @param {number} projectId
	 * @param {number} noteId
	 * @returns {Promise<{deleted: boolean}|null>}
	 */
	async deleteNote(projectId, noteId) {
		try {
			const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/notes/${noteId}`)
			const response = await axios.delete(url, {
				headers: {
					'OCS-APIRequest': 'true',
					'Content-Type': 'application/json',
				},
			})
			return response.data ?? null
		} catch (e) {
			console.error('Failed to delete note:', e)
			throw e
		}
	}

    /**
     * 
     * @param {string} userId 
     * 
     * @returns {Promise<any[]>}
     */
    async fetchProjectsByUser(userId) {
        try {
            const url = generateUrl(`/apps/projectcreatoraio/api/v1/users/${userId}/projects`);
            const response = await axios.get(url, {
                headers: {
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/json'
                }
            });
            return response.data ?? [];
        } catch(e) {
            console.error('Failed to fetch user projects', e);
            return [];
        }
    }

    /**
     * get projects by name
     * @param {string} query
     */
    async search(query) {
        try {
            const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/search`);
            
            const params = new URLSearchParams();
            params.append('search', query);
    
            const response = await axios.get(`${url}?${params.toString()}`, {
                headers: {
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/json'
                }
            });
    
            return response.data ?? [];

        } catch(e) {
            console.error("Failed to search projects", e);
            return [];
        }
    }
}
