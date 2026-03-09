import { t } from '@nextcloud/l10n'

export const PROJECT_STATUS = Object.freeze({
	ARCHIVED: 0,
	ACTIVE: 1,
	WAITING_ON_CUSTOMER: 2,
	ON_HOLD: 3,
	DONE: 4,
})

export const PROJECT_STATUS_OPTIONS = Object.freeze([
	Object.freeze({
		value: PROJECT_STATUS.ACTIVE,
		filterValue: 'active',
		label: t('projectcreatoraio', 'Active'),
		shortLabel: t('projectcreatoraio', 'Active'),
		pillClass: 'projects-home__status-pill--active',
		badgeClass: 'projects-home__badge--success',
	}),
	Object.freeze({
		value: PROJECT_STATUS.WAITING_ON_CUSTOMER,
		filterValue: 'waiting',
		label: t('projectcreatoraio', 'Waiting on Cust'),
		shortLabel: t('projectcreatoraio', 'Waiting'),
		pillClass: 'projects-home__status-pill--waiting',
		badgeClass: 'projects-home__badge--warning',
	}),
	Object.freeze({
		value: PROJECT_STATUS.ON_HOLD,
		filterValue: 'hold',
		label: t('projectcreatoraio', 'On Hold'),
		shortLabel: t('projectcreatoraio', 'On Hold'),
		pillClass: 'projects-home__status-pill--hold',
		badgeClass: 'projects-home__badge--muted',
	}),
	Object.freeze({
		value: PROJECT_STATUS.DONE,
		filterValue: 'done',
		label: t('projectcreatoraio', 'Done'),
		shortLabel: t('projectcreatoraio', 'Done'),
		pillClass: 'projects-home__status-pill--done',
		badgeClass: 'projects-home__badge--success',
	}),
	Object.freeze({
		value: PROJECT_STATUS.ARCHIVED,
		filterValue: 'archived',
		label: t('projectcreatoraio', 'Archived'),
		shortLabel: t('projectcreatoraio', 'Archived'),
		pillClass: 'projects-home__status-pill--archived',
		badgeClass: 'projects-home__badge--muted',
	}),
])

export const PROJECT_STATUS_FILTER_OPTIONS = Object.freeze([
	Object.freeze({ value: 'all', label: t('projectcreatoraio', 'All') }),
	...PROJECT_STATUS_OPTIONS.map((option) => Object.freeze({
		value: option.filterValue,
		label: option.label,
	})),
])

function getDefaultStatusOption() {
	return PROJECT_STATUS_OPTIONS[0]
}

export function normalizeProjectStatus(status) {
	const normalized = Number(status)
	return Number.isInteger(normalized) ? normalized : PROJECT_STATUS.ACTIVE
}

export function getProjectStatusOption(status) {
	const normalized = normalizeProjectStatus(status)
	return PROJECT_STATUS_OPTIONS.find((option) => option.value === normalized) || getDefaultStatusOption()
}

export function getProjectStatusLabel(status) {
	return getProjectStatusOption(status).label
}

export function getProjectStatusShortLabel(status) {
	return getProjectStatusOption(status).shortLabel
}

export function getProjectStatusBadgeClass(status) {
	return getProjectStatusOption(status).badgeClass
}

export function getProjectStatusPillClass(status) {
	return getProjectStatusOption(status).pillClass
}

export function matchesProjectStatusFilter(filterValue, status) {
	if (filterValue === 'all') {
		return true
	}

	return getProjectStatusOption(status).filterValue === filterValue
}
