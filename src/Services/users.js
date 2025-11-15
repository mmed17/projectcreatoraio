import axios from "axios";
import { generateOcsUrl } from '@nextcloud/router';

export class UsersService {

    instance = null;

    constructor() {}

    /**
     * @returns {UsersSerice}
     */
    static getInstance() {
        if(this.instance) {
            return this.instance;
        }

        return new UsersService();
    }

    /**
     * Search users, either globally or within a specific group.
     * @param {string} query The search term
     * @param {string|null} organizationId The group ID to search in (if any)
     * @returns {Promise<any[]>}
     */
    async search(query, organizationId = null) {
        if (!query.trim()) {
            return [];
        }

        // If an organization is selected, use the new group-specific endpoint
        if (organizationId) {
            return this.searchInGroup(query, organizationId);
        } 
        
        // Otherwise, use the old global search
        return this.searchGlobal(query);
    }

    /**
     * Search users
     * @param {string} query 
     * @returns {Promise<any[]>}
     */
    async searchGlobal(query) {
        if (!query.trim()) {
            return [];
        }

        try {
            const response = await axios.get('/ocs/v1.php/cloud/users', {
                params: {
                    search: query,
                    limit: 20
                },
                headers: { 
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            });

            const userIds = response.data.ocs.data.users;
            const users = [];

            for( let i = 0; i < userIds.length; i++) {
                const userId  = userIds[i];
                const details = await this.fetchDetails(userId);
                
                if(details) {
                    users.push({
                        id: details.id,
                        user: details.id,
                        label: details.displayName,
                        displayName: details.displayName,
                        subname: details.email,
                    });
                }
            }

            return users;

        } catch (error) {
            console.error('Error fetching users:', error);
            return [];
        }
    }

    /**
     * 
     * @param {string} userId 
     */
    async fetchDetails(userId) {
        try {
            const response = await axios.get(`/ocs/v1.php/cloud/users/${userId}`, {
                headers: {
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            });

            const details = response.data.ocs.data;
            return {
                id: details.id,
                displayName: details.displayname,
                email: details.email ?? details.phone,
                status: details.status
            };
        } catch (error) {
            console.error(`Error getting details for user ${userId}:`, error);
        }
    }

    async searchInGroup(query, organizationId) {
        const url = generateOcsUrl(`cloud/groups/${organizationId}/users/details`, 2);
        
        try {
            const response = await axios.get(url, {
                params: {
                    search: query,
                    limit: 25,
                    offset: 0,
                },
                headers: { 
                    'OCS-APIRequest': 'true',
                }
            });

            // This endpoint should return full user objects
            const users = response.data.ocs.data.users;

            // Format for NcSelectUsers
            return Object.entries(users).map(([id, user]) => ({
                id: user.id,
                user: user.id,
                label: user.displayName,
                displayName: user.displayName,
                subname: user.email,
            }));

        } catch (error) {
            console.error(`Error searching users in group ${organizationId}:`, error);
            return [];
        }
    }
}