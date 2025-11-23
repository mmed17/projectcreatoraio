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
    groupId = null;

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
     * @param {string[]} members 
     */
    constructor(name='', number='', description='', type=undefined, members=[], groupId = '') {
        this.name = name.trim();
        this.number = number.trim();
        this.description = description.trim();
        this.type = type;
        this.members = members;
        this.groupId = groupId.trim();
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
            groupId: this.groupId,
            members: this.members,
        };
    }
}