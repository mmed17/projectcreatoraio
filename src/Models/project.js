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
    type = null;

    /**
     * @type {number|null}
     */
    organizationId = null;

    /**
     * @type {string[]}
     */
    members = [];

    /**
     * 
     * @param {string} name 
     * @param {string} number 
     * @param {string} description 
     * @param {number} type 
     * @param {string[]} members 
     * @param {number|null} organizationId
     */
    constructor(name = '', number = '', description = '', type = undefined, members = [], organizationId = null) {
        this.name = name.trim();
        this.number = number.trim();
        this.description = description.trim();
        this.type = type;
        this.members = members;
        this.organizationId = organizationId;
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
            organizationId: this.organizationId,
            members: this.members,
        };
    }
}
