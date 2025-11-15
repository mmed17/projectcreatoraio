// Save this as 'Services/GroupsService.js'

import axios from '@nextcloud/axios';
import { generateOcsUrl } from '@nextcloud/router';

export class GroupsService {
    static instance = null;

    static getInstance() {
        if (!GroupsService.instance) {
            GroupsService.instance = new GroupsService();
        }
        return GroupsService.instance;
    }

    /**
     * Search groups by name
     * @param {string} query The search term
     * @param {AbortSignal} [signal] Optional AbortSignal to cancel requests
     * @returns {Promise<any[]>}
     */
    async search(query, signal) {
        // Return empty array if search is empty
        if (!query.trim()) {
            return [];
        }

        // Use the OCS v2 endpoint you provided
        const url = generateOcsUrl('cloud/groups/details', 2);
        
        try {
            const response = await axios.get(url, {
                params: {
                    search: query, // This is the key change
                    limit: 25,     // Limit results for performance
                    offset: 0,
                },
                headers: { 
                    'OCS-APIRequest': 'true',
                },
                signal, // Pass the abort signal
            });

            const groups = response.data.ocs.data.groups;

            // Format for NcSelect: { id: 'group_id', label: 'Group Name' }
            return groups.map(group => ({
                id: group.id,
                label: group.displayname,
                subname: `Group ID: ${group.id}`, // Extra info
            }));

        } catch (error) {
            if (axios.isCancel(error)) {
                console.log('Group search request canceled');
            } else {
                console.error('Error searching groups:', error);
            }
            return []; // Return empty array on failure
        }
    }

    /**
     * Fetches details for a single group by its ID.
     * This is needed to show the label of a pre-selected group.
     * @param {string} groupId 
     * @returns {Promise<object|null>}
     */
    async getDetails(groupId) {
        if (!groupId) {
            return null;
        }

        const url = generateOcsUrl('cloud/groups/details', 2);
        try {
            // We can "search" for the exact ID
            const response = await axios.get(url, {
                params: {
                    search: groupId,
                    limit: 1,
                },
                headers: { 'OCS-APIRequest': 'true' }
            });

            const groups = response.data.ocs.data.groups;

            // Ensure we got the exact match
            if (groups.length > 0 && groups[0].id === groupId) {
                 return {
                    id: groups[0].id,
                    label: groups[0].displayname,
                    subname: `Group ID: ${groups[0].id}`
                };
            }
            return null; // Not found

        } catch (error) {
            console.error(`Error fetching group details for ${groupId}:`, error);
            return null;
        }
    }
}