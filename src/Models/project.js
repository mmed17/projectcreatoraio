export class Project {
    static toLocalISODate(date = new Date()) {
        const y = date.getFullYear()
        const m = String(date.getMonth() + 1).padStart(2, '0')
        const d = String(date.getDate()).padStart(2, '0')
        return `${y}-${m}-${d}`
    }

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
     * @type {string}
     */
    client_name = '';

    /**
     * @type {string}
     */
    client_role = '';

    /**
     * @type {string}
     */
    client_phone = '';

    /**
     * @type {string}
     */
    client_email = '';

    /**
     * @type {string}
     */
    client_address = '';

    /**
     * @type {string}
     */
    loc_street = '';

    /**
     * @type {string}
     */
    loc_city = '';

    /**
     * @type {string}
     */
    loc_zip = '';

    /**
     * @type {number}
     */
    type = null;

    /**
     * @type {number|null}
     */
    organizationId = null;

    /**
     * @type {string}
     */
    request_date = '';

    /**
     * @type {string}
     */
    desired_execution_date = '';

    /**
     * @type {number}
     */
    required_preparation_days = 0;

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

        const today = Project.toLocalISODate(new Date())
        this.request_date = today
        this.desired_execution_date = today
        this.required_preparation_days = 0
    }

    get isValid() {
        return this.name || this.number || this.type >= 0 || this.members.length > 0;
    }

    toJson() {
        return {
            name: this.name,
            number: this.number,
            description: this.description,
            client_name: this.client_name,
            client_role: this.client_role,
            client_phone: this.client_phone,
            client_email: this.client_email,
            client_address: this.client_address,
            loc_street: this.loc_street,
            loc_city: this.loc_city,
            loc_zip: this.loc_zip,
            type: this.type,
            organizationId: this.organizationId,
            members: this.members,
            request_date: this.request_date,
            desired_execution_date: this.desired_execution_date,
            required_preparation_days: this.required_preparation_days,
        };
    }
}
