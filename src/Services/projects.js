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
        try {
            const url = generateUrl('/apps/projectcreatoraio/api/v1/projects/context')
            const response = await axios.get(url, {
                headers: {
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/json'
                }
            });

            return response.data ?? null;
        } catch (e) {
            console.error('Failed to fetch projects context:', e);
            return null;
        }
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
