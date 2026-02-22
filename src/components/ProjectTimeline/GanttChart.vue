<template>
	<div class="gantt-chart-section">
		<div class="section-header">
			<h3 class="modern-header">Project Timeline</h3>
			<div class="header-actions">
				<div class="timeline-nav">
					<NcButton type="tertiary" aria-label="Previous" @click="navigatePrev">
						<template #icon>
							<ChevronLeft :size="20" />
						</template>
					</NcButton>
					<NcButton type="tertiary" class="today-btn" @click="navigateToday">Today</NcButton>
					<NcButton type="tertiary" aria-label="Next" @click="navigateNext">
						<template #icon>
							<ChevronRight :size="20" />
						</template>
					</NcButton>
				</div>
				<div class="timeline-zoom">
					<NcButton type="tertiary" aria-label="Zoom out" @click="zoomOut">-</NcButton>
					<span class="zoom-label">Zoom</span>
					<NcButton type="tertiary" aria-label="Zoom in" @click="zoomIn">+</NcButton>
				</div>
				<NcButton v-if="isAdmin" type="primary" @click="openAddModal">
					<template #icon>
						<Plus :size="16" />
					</template>
					Add Phase
				</NcButton>
			</div>
		</div>

		<div class="detail-card">
			<div v-if="loading" class="loading-state">
				<NcLoadingIcon :size="32" />
				<p>Loading timeline...</p>
			</div>

			<div v-else-if="items.length === 0" class="empty-state">
				<p>No timeline phases defined yet.</p>
				<p class="empty-hint">Click "Add Phase" in the top right to get started.</p>
			</div>

			<div v-else class="gantt-container">
				<div class="gantt-grid" :class="{ 'gantt-grid--with-actions': isAdmin }">
					<div class="gantt-fixed">
						<div class="gantt-fixed-header" :style="{ height: timelineHeaderHeight + 'px' }">Phase</div>
						<div v-for="item in items" :key="`fixed-${item.id}`" class="gantt-fixed-row">
							<div class="phase-name-line">
								<span class="phase-name">{{ item.label }}</span>
								<span class="phase-duration" :title="formatWeeksLabel(item)">{{ formatWeeks(item) }}</span>
							</div>
							<span class="phase-dates">{{ formatDate(item.startDate) }} - {{ formatDate(item.endDate) }}</span>
						</div>
					</div>

					<div
						ref="scrollEl"
						class="gantt-scroll"
						:class="{ 'gantt-scroll--dragging': isDragging }"
						@pointerdown="onPointerDown"
						@pointermove="onPointerMove"
						@pointerup="onPointerUp"
						@pointercancel="onPointerUp"
						@pointerleave="onPointerUp">
						<div class="gantt-timeline" :style="{ width: totalTimelineWidth + 'px' }">
							<div class="gantt-timeline-header" :style="{ height: timelineHeaderHeight + 'px' }">
								<div v-if="spanMultipleYears" class="year-row">
									<span
										v-for="year in visibleYears"
										:key="year.key"
										class="year-label"
										:style="{ width: year.width + 'px' }">{{ year.label }}</span>
								</div>
								<div class="month-row">
									<span
										v-for="month in visibleMonths"
										:key="month.key"
										class="month-label"
										:class="{ compact: month.width < 60 }"
										:style="{ width: month.width + 'px' }">
										{{ month.width < 40 ? '' : month.label }}
									</span>
								</div>
								<div class="week-row">
									<span
										v-for="week in visibleIsoWeeks"
										:key="week.key"
										class="week-label"
										:style="{ width: week.width + 'px' }"
										:title="week.tooltip">
										{{ week.width < 16 ? '' : week.label }}
									</span>
								</div>
							</div>

							<div class="today-marker" :style="{ left: todayOffset + 'px' }">
								<div class="today-line"></div>
								<span class="today-label">Today</span>
							</div>

							<div v-for="item in items" :key="`bar-${item.id}`" class="gantt-timeline-row">
								<div class="timeline-track">
									<div class="timeline-bar" :style="getBarStyle(item)" :title="`${item.label}: ${formatDate(item.startDate)} - ${formatDate(item.endDate)} (${formatWeeksLabel(item)})`"></div>
								</div>
							</div>
						</div>
					</div>

					<div v-if="isAdmin" class="gantt-actions">
						<div class="gantt-actions-header" :style="{ height: timelineHeaderHeight + 'px' }"></div>
						<div v-for="item in items" :key="`actions-${item.id}`" class="gantt-actions-row">
							<div v-if="!isSystemItem(item)" class="reorder-buttons">
								<NcButton
									type="tertiary"
									aria-label="Move up"
									:disabled="!canMoveUp(item)"
									@click="movePhaseUp(item)">
									<template #icon>
										<ChevronUp :size="16" />
									</template>
								</NcButton>
								<NcButton
									type="tertiary"
									aria-label="Move down"
									:disabled="!canMoveDown(item)"
									@click="movePhaseDown(item)">
									<template #icon>
										<ChevronDown :size="16" />
									</template>
								</NcButton>
							</div>
							<NcButton type="tertiary" aria-label="Edit" @click="openEditModal(item)">
								<template #icon>
									<Pencil :size="16" />
								</template>
							</NcButton>
							<NcButton v-if="!isSystemItem(item)" type="error" aria-label="Delete" @click="confirmDelete(item)">
								<template #icon>
									<Delete :size="16" />
								</template>
							</NcButton>
						</div>
					</div>
				</div>

				<div v-if="items.length > 1" class="phase-jumper">
					<span class="jumper-label">Jump to:</span>
					<button
						v-for="(item, index) in items"
						:key="item.id"
						class="phase-chip"
						:class="{ active: currentPhaseIndex === index }"
						:style="{ '--phase-color': item.color }"
						@click="navigateToPhase(index)">
						{{ item.label }}
					</button>
				</div>
			</div>
		</div>

		<NcModal v-if="showModal" @close="closeModal">
			<div class="phase-form">
				<h3>{{ editingItem ? 'Edit Phase' : 'Add Phase' }}</h3>
				<div class="form-field">
					<label>Phase Name</label>
					<NcTextField :value.sync="form.label" :disabled="isEditingSystemItem" placeholder="e.g., Development, QA, Production" />
				</div>
				<div class="form-row">
					<div class="form-field">
						<label>Start Date</label>
						<NcTextField type="date" :value.sync="form.startDate" />
					</div>
					<div class="form-field">
						<label>End Date</label>
						<NcTextField type="date" :value.sync="form.endDate" />
					</div>
				</div>
				<div class="form-field">
					<label>Color</label>
					<div class="color-picker">
						<button
							v-for="color in colorOptions"
							:key="color"
							class="color-option"
							:class="{ selected: form.color === color }"
							:style="{ backgroundColor: color }"
							@click="form.color = color"></button>
					</div>
				</div>
				<div class="form-actions">
					<NcButton @click="closeModal">Cancel</NcButton>
					<NcButton type="primary" :disabled="!canSave || saving" @click="saveItem">{{ saving ? 'Saving...' : 'Save' }}</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { NcButton, NcLoadingIcon, NcModal, NcTextField } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import ChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'

export default {
	name: 'GanttChart',
	components: {
		NcButton,
		NcLoadingIcon,
		NcModal,
		NcTextField,
		Plus,
		Pencil,
		Delete,
		ChevronLeft,
		ChevronRight,
		ChevronUp,
		ChevronDown,
	},
	props: {
		projectId: {
			type: Number,
			required: true,
		},
		isAdmin: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			loading: true,
			saving: false,
			items: [],
			showModal: false,
			editingItem: null,
			form: {
				label: '',
				startDate: '',
				endDate: '',
				color: '#3b82f6',
			},
			colorOptions: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'],
			dayWidth: 3,
			currentPhaseIndex: 0,
			isDragging: false,
			dragStartX: 0,
			dragStartScrollLeft: 0,
		}
	},
	computed: {
		canSave() {
			return this.form.label.trim() !== '' && this.form.startDate !== '' && this.form.endDate !== ''
		},
		isEditingSystemItem() {
			return !!(this.editingItem && this.editingItem.systemKey)
		},
		timelineRange() {
			if (this.items.length === 0) {
				const now = new Date()
				return { start: new Date(now.getFullYear(), now.getMonth(), 1), end: new Date(now.getFullYear(), now.getMonth() + 6, 0) }
			}
			let minDate = null
			let maxDate = null
			const today = this.toDateOnly(new Date())
			for (const item of this.items) {
				const start = this.parseDateOnly(item.startDate)
				const end = this.parseDateOnly(item.endDate)
				if (!minDate || start < minDate) minDate = start
				if (!maxDate || end > maxDate) maxDate = end
			}
			if (today < minDate) minDate = today
			if (today > maxDate) maxDate = today
			const paddedStart = new Date(minDate)
			paddedStart.setMonth(paddedStart.getMonth() - 1)
			paddedStart.setDate(1)
			const paddedEnd = new Date(maxDate)
			paddedEnd.setMonth(paddedEnd.getMonth() + 2)
			paddedEnd.setDate(0)
			return { start: paddedStart, end: paddedEnd }
		},
		totalDays() {
			const { start, end } = this.timelineRange
			return Math.ceil((end - start) / (1000 * 60 * 60 * 24))
		},
		totalTimelineWidth() {
			return this.totalDays * this.dayWidth
		},
		visibleMonths() {
			const months = []
			const { start, end } = this.timelineRange
			let cursor = new Date(start)
			while (cursor.getTime() <= end.getTime()) {
				const monthStart = new Date(cursor.getFullYear(), cursor.getMonth(), 1)
				const monthEnd = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0)
				const effectiveStart = monthStart < start ? start : monthStart
				const effectiveEnd = monthEnd > end ? end : monthEnd
				const days = Math.ceil((effectiveEnd - effectiveStart) / (1000 * 60 * 60 * 24)) + 1
				const width = days * this.dayWidth
				const labelFormat = this.spanMultipleYears ? { month: 'short' } : { month: 'short', year: 'numeric' }
				months.push({
					key: `${cursor.getFullYear()}-${cursor.getMonth()}`,
					label: cursor.toLocaleDateString('default', labelFormat),
					width,
					year: cursor.getFullYear(),
				})
				cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1)
			}
			return months
		},
		spanMultipleYears() {
			const { start, end } = this.timelineRange
			return start.getFullYear() !== end.getFullYear()
		},
		timelineHeaderHeight() {
			// Must match CSS heights for year/month/week rows.
			return this.spanMultipleYears ? 84 : 52
		},
		visibleYears() {
			if (!this.spanMultipleYears) return []
			const years = []
			const { start, end } = this.timelineRange
			for (let year = start.getFullYear(); year <= end.getFullYear(); year++) {
				const yearStart = new Date(Math.max(start.getTime(), new Date(year, 0, 1).getTime()))
				const yearEnd = new Date(Math.min(end.getTime(), new Date(year, 11, 31).getTime()))
				const days = Math.ceil((yearEnd - yearStart) / (1000 * 60 * 60 * 24)) + 1
				years.push({ key: `year-${year}`, label: year.toString(), width: days * this.dayWidth })
			}
			return years
		},
		visibleIsoWeeks() {
			const weeks = []
			const { start, end } = this.timelineRange
			let cursor = this.toDateOnly(start)
			while (cursor.getTime() <= end.getTime()) {
				const weekInfo = this.getIsoWeekInfo(cursor)
				const effectiveStart = cursor < start ? start : cursor
				const spanDays = Math.min(7, Math.floor((end - effectiveStart) / (1000 * 60 * 60 * 24)) + 1)
				weeks.push({
					key: `${weekInfo.isoYear}-W${weekInfo.isoWeek}`,
					label: `W${weekInfo.isoWeek}`,
					tooltip: `ISO Week ${weekInfo.isoWeek}, ${weekInfo.isoYear}`,
					width: spanDays * this.dayWidth,
				})
				cursor = new Date(cursor)
				cursor.setDate(cursor.getDate() + spanDays)
			}
			return weeks
		},
		todayOffset() {
			const { start } = this.timelineRange
			const today = this.toDateOnly(new Date())
			const days = Math.floor((today - start) / (1000 * 60 * 60 * 24))
			return days * this.dayWidth
		},
	},
	watch: {
		projectId: {
			handler(val) {
				if (val) this.loadItems()
			},
			immediate: true,
		},
	},
	methods: {
		isSystemItem(item) {
			return !!(item && item.systemKey)
		},
		canMoveUp(item) {
			const index = this.getItemIndex(item)
			if (index <= 0) return false
			return !this.isSystemItem(this.items[index - 1])
		},
		canMoveDown(item) {
			const index = this.getItemIndex(item)
			if (index < 0 || index >= this.items.length - 1) return false
			return !this.isSystemItem(this.items[index + 1])
		},
		async loadItems() {
			this.loading = true
			try {
				const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${this.projectId}/timeline`)
				const response = await axios.get(url)
				this.items = response.data || []
			} catch (error) {
				console.error('Error loading timeline:', error)
				this.items = []
			} finally {
				this.loading = false
			}
		},
		formatDate(dateStr) {
			if (!dateStr) return '-'
			const date = this.parseDateOnly(dateStr)
			const day = date.getDate().toString().padStart(2, '0')
			const month = (date.getMonth() + 1).toString().padStart(2, '0')
			const year = date.getFullYear()
			return `${day}/${month}/${year}`
		},
		parseDateOnly(value) {
			if (!value) return this.toDateOnly(new Date())
			return new Date(`${value}T00:00:00`)
		},
		toDateOnly(date) {
			const d = new Date(date)
			d.setHours(0, 0, 0, 0)
			return d
		},
		getIsoWeekInfo(date) {
			const d = this.toDateOnly(date)
			const utcDate = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()))
			const day = utcDate.getUTCDay() || 7
			utcDate.setUTCDate(utcDate.getUTCDate() + 4 - day)
			const isoYear = utcDate.getUTCFullYear()
			const yearStart = new Date(Date.UTC(isoYear, 0, 1))
			const isoWeek = Math.ceil(((utcDate - yearStart) / (1000 * 60 * 60 * 24) + 1) / 7)
			return { isoWeek, isoYear }
		},
		getBarStyle(item) {
			const { start } = this.timelineRange
			const itemStart = this.parseDateOnly(item.startDate)
			const itemEnd = this.parseDateOnly(item.endDate)
			const offsetDays = Math.floor((itemStart - start) / (1000 * 60 * 60 * 24))
			const durationDays = Math.floor((itemEnd - itemStart) / (1000 * 60 * 60 * 24)) + 1
			const leftPx = offsetDays * this.dayWidth
			return { left: `${leftPx}px`, width: `${durationDays * this.dayWidth}px`, backgroundColor: item.color || '#3b82f6' }
		},
		getDurationDays(item) {
			const start = this.parseDateOnly(item.startDate)
			const end = this.parseDateOnly(item.endDate)
			return Math.max(1, Math.floor((end - start) / (1000 * 60 * 60 * 24)) + 1)
		},
		formatWeeks(item) {
			const days = this.getDurationDays(item)
			const weeks = days / 7
			const fixed = Math.abs(weeks - Math.round(weeks)) < 1e-9 ? String(Math.round(weeks)) : weeks.toFixed(1)
			return `${fixed}w`
		},
		formatWeeksLabel(item) {
			const days = this.getDurationDays(item)
			const weeks = days / 7
			const fixed = Math.abs(weeks - Math.round(weeks)) < 1e-9 ? String(Math.round(weeks)) : weeks.toFixed(1)
			return `${fixed} week${weeks === 1 ? '' : 's'} (${days} day${days === 1 ? '' : 's'})`
		},
		openAddModal() {
			this.editingItem = null
			this.form = { label: '', startDate: '', endDate: '', color: '#3b82f6' }
			this.showModal = true
		},
		openEditModal(item) {
			this.editingItem = item
			this.form = { label: item.label, startDate: item.startDate, endDate: item.endDate, color: item.color || '#3b82f6' }
			this.showModal = true
		},
		closeModal() {
			this.showModal = false
			this.editingItem = null
		},
		async saveItem() {
			if (!this.canSave) return
			this.saving = true
			try {
				const baseUrl = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${this.projectId}/timeline`)
				if (this.editingItem) {
					await axios.put(`${baseUrl}/${this.editingItem.id}`, this.form)
				} else {
					await axios.post(baseUrl, this.form)
				}
				this.closeModal()
				await this.loadItems()
			} catch (error) {
				console.error('Error saving phase:', error)
			} finally {
				this.saving = false
			}
		},
		async confirmDelete(item) {
			if (this.isSystemItem(item)) return
			if (!confirm(`Are you sure you want to delete "${item.label}"?`)) return
			try {
				const url = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${this.projectId}/timeline/${item.id}`)
				await axios.delete(url)
				await this.loadItems()
			} catch (error) {
				console.error('Error deleting phase:', error)
			}
		},
		navigatePrev() {
			const monthPx = 30 * this.dayWidth
			this.scrollBy(-monthPx)
		},
		navigateNext() {
			const monthPx = 30 * this.dayWidth
			this.scrollBy(monthPx)
		},
		navigateToday() {
			const el = this.$refs.scrollEl
			if (!el) return
			const containerWidth = el.clientWidth || 400
			const target = Math.max(0, this.todayOffset - containerWidth / 2)
			el.scrollLeft = target
		},
		navigateToPhase(index) {
			if (index < 0 || index >= this.items.length) return
			this.currentPhaseIndex = index
			const item = this.items[index]
			const { start } = this.timelineRange
			const itemStart = this.parseDateOnly(item.startDate)
			const offsetDays = Math.floor((itemStart - start) / (1000 * 60 * 60 * 24))
			const offsetPx = offsetDays * this.dayWidth
			const el = this.$refs.scrollEl
			if (!el) return
			el.scrollLeft = Math.max(0, offsetPx - 50)
		},
		scrollBy(px) {
			const el = this.$refs.scrollEl
			if (!el) return
			el.scrollLeft = Math.max(0, el.scrollLeft + px)
		},
		setZoom(nextDayWidth) {
			const el = this.$refs.scrollEl
			if (!el) {
				this.dayWidth = nextDayWidth
				return
			}
			const oldDayWidth = this.dayWidth
			const centerPx = el.scrollLeft + el.clientWidth / 2
			const centerDays = oldDayWidth > 0 ? centerPx / oldDayWidth : 0
			this.dayWidth = nextDayWidth
			this.$nextTick(() => {
				const target = Math.max(0, centerDays * this.dayWidth - el.clientWidth / 2)
				el.scrollLeft = target
			})
		},
		zoomIn() {
			const levels = [3, 4, 6, 8, 12]
			const idx = Math.max(0, levels.indexOf(this.dayWidth))
			const next = levels[Math.min(levels.length - 1, idx + 1)]
			this.setZoom(next)
		},
		zoomOut() {
			const levels = [3, 4, 6, 8, 12]
			const idx = Math.max(0, levels.indexOf(this.dayWidth))
			const next = levels[Math.max(0, idx - 1)]
			this.setZoom(next)
		},
		onPointerDown(e) {
			if (e.button !== undefined && e.button !== 0) return
			const el = this.$refs.scrollEl
			if (!el) return
			this.isDragging = true
			this.dragStartX = e.clientX
			this.dragStartScrollLeft = el.scrollLeft
			try {
				e.currentTarget.setPointerCapture(e.pointerId)
			} catch (err) {
				// ignore
			}
		},
		onPointerMove(e) {
			if (!this.isDragging) return
			const el = this.$refs.scrollEl
			if (!el) return
			e.preventDefault()
			const dx = e.clientX - this.dragStartX
			el.scrollLeft = Math.max(0, this.dragStartScrollLeft - dx)
		},
		onPointerUp() {
			this.isDragging = false
		},
		getItemIndex(item) {
			return this.items.findIndex((i) => i.id === item.id)
		},
		async movePhaseUp(item) {
			const index = this.getItemIndex(item)
			if (!this.canMoveUp(item)) return
			await this.swapPhaseOrder(index, index - 1)
		},
		async movePhaseDown(item) {
			const index = this.getItemIndex(item)
			if (!this.canMoveDown(item)) return
			await this.swapPhaseOrder(index, index + 1)
		},
		async swapPhaseOrder(indexA, indexB) {
			const itemA = this.items[indexA]
			const itemB = this.items[indexB]
			if (!itemA || !itemB) return
			if (this.isSystemItem(itemA) || this.isSystemItem(itemB)) return
			try {
				const baseUrl = generateUrl(`/apps/projectcreatoraio/api/v1/projects/${this.projectId}/timeline`)
				await Promise.all([
					axios.put(`${baseUrl}/${itemA.id}`, { orderIndex: indexB }),
					axios.put(`${baseUrl}/${itemB.id}`, { orderIndex: indexA }),
				])
				const temp = this.items[indexA]
				this.$set(this.items, indexA, this.items[indexB])
				this.$set(this.items, indexB, temp)
			} catch (error) {
				console.error('Error reordering phases:', error)
				await this.loadItems()
			}
		},
	},
}
</script>

<style scoped>
.gantt-chart-section { margin-top: 24px; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.section-header .modern-header { margin: 0; font-size: 18px; font-weight: 600; }
.detail-card { background: var(--color-main-background); border: 1px solid var(--color-border); border-radius: 12px; padding: 20px; overflow: hidden; }
.loading-state, .empty-state { text-align: center; padding: 32px; color: var(--color-text-maxcontrast); }
.loading-state p, .empty-state p { margin: 0 0 16px; }
.gantt-container { min-width: 100%; }
.gantt-grid { display: grid; grid-template-columns: 220px 1fr; border: 1px solid var(--color-border); border-radius: 10px; overflow: hidden; }
.gantt-grid--with-actions { grid-template-columns: 220px 1fr 132px; }

.gantt-fixed { background: var(--color-main-background); border-right: 1px solid var(--color-border); }
.gantt-fixed-header { display: flex; align-items: center; padding: 12px; font-weight: 600; font-size: 13px; color: var(--color-text-maxcontrast); background: var(--color-background-dark); border-bottom: 1px solid var(--color-border); }
.gantt-fixed-row { display: flex; flex-direction: column; justify-content: center; padding: 10px 12px; min-height: 56px; border-bottom: 1px solid var(--color-border); }
.gantt-fixed-row:last-child { border-bottom: none; }
.phase-name-line { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
.phase-name { font-weight: 600; font-size: 14px; }
.phase-duration { font-size: 11px; font-weight: 700; color: var(--color-text-maxcontrast); background: var(--color-background-hover); border: 1px solid var(--color-border); padding: 2px 6px; border-radius: 999px; white-space: nowrap; }
.phase-dates { font-size: 11px; color: var(--color-text-maxcontrast); margin-top: 4px; }

.gantt-scroll { overflow-x: auto; overflow-y: hidden; position: relative; cursor: grab; touch-action: pan-y; background: var(--color-main-background); }
.gantt-scroll--dragging { cursor: grabbing; user-select: none; }
.gantt-timeline { position: relative; }
.gantt-timeline-header { background: var(--color-background-dark); border-bottom: 1px solid var(--color-border); }

.gantt-timeline-row { min-height: 56px; border-bottom: 1px solid var(--color-border); display: flex; align-items: center; }
.gantt-timeline-row:last-child { border-bottom: none; }

.gantt-actions { background: var(--color-main-background); border-left: 1px solid var(--color-border); }
.gantt-actions-header { background: var(--color-background-dark); border-bottom: 1px solid var(--color-border); }
.gantt-actions-row { display: flex; align-items: center; gap: 6px; padding: 8px; min-height: 56px; border-bottom: 1px solid var(--color-border); }
.gantt-actions-row:last-child { border-bottom: none; }

.reorder-buttons { display: flex; flex-direction: column; gap: 2px; margin-right: 4px; }
.reorder-buttons button { padding: 0; min-height: 24px; min-width: 24px; }
.reorder-buttons button:disabled { opacity: 0.3; cursor: not-allowed; }
.year-row { display: flex; height: 32px; border-bottom: 1px solid var(--color-border); background: var(--color-background-darker, #e5e7eb); }
.year-label { display: flex; align-items: center; justify-content: center; padding: 6px 8px; font-size: 13px; font-weight: 700; color: var(--color-main-text); border-right: 1px solid var(--color-border); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.month-row { display: flex; height: 28px; border-bottom: 1px solid var(--color-border); }
.month-label { display: flex; align-items: center; justify-content: center; padding: 6px 4px; font-size: 11px; font-weight: 500; color: var(--color-text-maxcontrast); border-right: 1px solid var(--color-border); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-height: 28px; }
.month-label.compact { font-size: 10px; padding: 4px 2px; }
.week-row { display: flex; height: 24px; }
.week-label { display: flex; align-items: center; justify-content: center; padding: 3px 2px; font-size: 10px; font-weight: 700; color: var(--color-text-lighter); border-right: 1px solid var(--color-border); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-height: 24px; letter-spacing: 0.02em; }
.timeline-track { position: relative; height: 32px; width: 100%; margin: 8px 0; }
.timeline-bar { position: absolute; top: 0; height: 100%; border-radius: 4px; min-width: 6px; cursor: pointer; transition: transform 0.2s ease; }
.timeline-bar:hover { transform: scaleY(1.1); }
.phase-form { padding: 20px; min-width: 400px; }
.phase-form h3 { margin: 0 0 20px; font-size: 18px; }
.form-field { margin-bottom: 16px; }
.form-field label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: var(--color-text-maxcontrast); }
.form-row { display: flex; gap: 16px; }
.form-row .form-field { flex: 1; }
.color-picker { display: flex; gap: 8px; flex-wrap: wrap; }
.color-option { width: 32px; height: 32px; border-radius: 6px; border: 2px solid transparent; cursor: pointer; transition: all 0.2s ease; }
.color-option:hover { transform: scale(1.1); }
.color-option.selected { border-color: var(--color-main-text); box-shadow: 0 0 0 2px var(--color-main-background), 0 0 0 4px var(--color-primary-element); }
.form-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; }
.header-actions { display: flex; align-items: center; gap: 12px; }
.timeline-nav { display: flex; align-items: center; gap: 4px; background: var(--color-background-dark); border-radius: 8px; padding: 4px; }
.timeline-nav .today-btn { min-width: auto; padding: 6px 12px; font-weight: 500; }
.timeline-zoom { display: flex; align-items: center; gap: 6px; background: var(--color-background-dark); border-radius: 8px; padding: 4px; }
.zoom-label { font-size: 12px; font-weight: 600; color: var(--color-text-maxcontrast); padding: 0 6px; }

.today-marker { position: absolute; top: 0; bottom: 0; z-index: 10; pointer-events: none; }
.today-line { width: 2px; height: 100%; background: #dc2626; position: absolute; left: 0; opacity: 0.98; }
.today-label { position: absolute; top: 6px; left: 50%; transform: translateX(-50%); font-size: 10px; font-weight: 700; color: #dc2626; background: var(--color-main-background); padding: 2px 6px; border-radius: 4px; white-space: nowrap; box-shadow: 0 1px 0 rgba(0,0,0,0.06); border: 1px solid rgba(220, 38, 38, 0.35); }
.phase-jumper { display: flex; align-items: center; gap: 8px; padding: 16px 12px; border-top: 1px solid var(--color-border); flex-wrap: wrap; }
.jumper-label { font-size: 12px; font-weight: 500; color: var(--color-text-maxcontrast); }
.phase-chip { padding: 6px 12px; border-radius: 16px; font-size: 12px; font-weight: 500; border: 1px solid var(--color-border); background: var(--color-main-background); color: var(--color-main-text); cursor: pointer; transition: all 0.2s ease; }
.phase-chip:hover { border-color: var(--phase-color, var(--color-primary-element)); background: var(--color-background-hover); }
.phase-chip.active { background: var(--phase-color, var(--color-primary-element)); color: #fff; border-color: var(--phase-color, var(--color-primary-element)); }
.empty-hint { font-size: 13px; color: var(--color-text-lighter); margin-top: 8px; }
@media (max-width: 900px) {
	.header-actions { flex-direction: column; align-items: stretch; }
	.form-row { flex-direction: column; gap: 0; }
	.gantt-grid, .gantt-grid--with-actions { grid-template-columns: 1fr; }
	.gantt-fixed { border-right: none; border-bottom: 1px solid var(--color-border); }
	.gantt-actions { border-left: none; border-top: 1px solid var(--color-border); }
}
</style>
