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
     * @type {string}
     */
    groupId = null;

    /**
     * @type {string[]}
     */
    members = null;

    /**
     * @type {string|null} - Date string in YYYY-MM-DD format
     */
    dateStart = null;

    /**
     * @type {string|null} - Date string in YYYY-MM-DD format
     */
    dateEnd = null;

    /**
     * 
     * @param {string} name 
     * @param {string} number 
     * @param {string} description 
     * @param {number} type 
     * @param {string[]} members 
     * @param {string} groupId
     * @param {string|null} dateStart
     * @param {string|null} dateEnd
     */
    constructor(name = '', number = '', description = '', type = undefined, members = [], groupId = '', dateStart = null, dateEnd = null) {
        this.name = name.trim();
        this.number = number.trim();
        this.description = description.trim();
        this.type = type;
        this.members = members;
        this.groupId = groupId.trim();
        this.dateStart = dateStart;
        this.dateEnd = dateEnd;
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
            date_start: this.dateStart,
            date_end: this.dateEnd,
        };
    }
}