import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export class OrganizationsService {
    static instance = null;

    static getInstance() {
        if (!OrganizationsService.instance) {
            OrganizationsService.instance = new OrganizationsService();
        }

        return OrganizationsService.instance;
    }

    /**
     * @param {string} query
     * @param {number} limit
     * @returns {Promise<Array<{id:number,label:string,subname:string}>>}
     */
    async search(query, limit = 25) {
        const url = generateOcsUrl('apps/organization/organizations', 2);

        const response = await axios.get(url, {
            params: {
                search: query || '',
                limit,
                offset: 0,
            },
            headers: {
                'OCS-APIRequest': 'true',
            },
        });

        const organizations = response?.data?.ocs?.data?.organizations ?? [];
        return organizations.map((org) => ({
            id: Number(org.id),
            label: org.displayname,
            subname: `Organization ID: ${org.id}`,
        }));
    }

    /**
     * @param {number|string|null} organizationId
     * @returns {Promise<{id:number,label:string,subname:string}|null>}
     */
    async getDetails(organizationId) {
        if (organizationId === null || organizationId === undefined || organizationId === '') {
            return null;
        }

        const url = generateOcsUrl(`apps/organization/organizations/${organizationId}`, 2);

        const response = await axios.get(url, {
            headers: {
                'OCS-APIRequest': 'true',
            },
        });

        const org = response?.data?.ocs?.data?.organization;
        if (!org) {
            return null;
        }

        return {
            id: Number(org.id),
            label: org.name || org.displayname || `Organization ${org.id}`,
            subname: `Organization ID: ${org.id}`,
        };
    }
}
