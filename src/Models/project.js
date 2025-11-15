export class Project {
    /**
     * @type {string}
     */
    name = '';

    /**
     * @type {string}
     */
    number = '';

    /**
     * @type {string}
     */
    description = '';

    /**
     * @type {number}
     */

    /**
     * @type {number}
     */
    type = null;

    /**
     * @type {string}
     */
    address = null;

    /**
     * @type {string}
     */
    organizationId = null;

    /**
     * @type {string[]}
     */
    members = null;

    /**
     * 
     * @param {string} name 
     * @param {string} number 
     * @param {string} description 
     * @param {number} type 
     * @param {string} address 
     * @param {string[]} members 
     */
    constructor(name='', number='', description='', type=undefined, address='', members=[], organizationId = '') {
        this.name = name.trim();
        this.number = number.trim();
        this.description = description.trim();
        this.type = type;
        this.address = address.trim();
        this.members = members;
        this.organizationId = organizationId.trim();
    }

    get isValid() {
        return this.name || this.number || this.type >= 0 || this.members.length > 0;
    }

    toJson() {
        return {
            name: this.name,
            number: this.number,
            description: this.description,
            type: this.type,
            address: this.address,
            organizationId: this.organizationId,
            members: this.members,
        };
    }
}