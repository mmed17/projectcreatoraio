<template>
	<div class="deck-board">
		<div v-if="!boardId" class="deck-board__empty">No Deck board linked to this project.</div>

		<div v-else>
			<div class="deck-board__top">
				<div class="deck-board__meta">
					<div class="deck-board__title">
						<span class="deck-board__title-text">{{ boardTitle }}</span>
						<span class="deck-board__badge">#{{ boardId }}</span>
						<span v-if="!canEdit" class="deck-board__badge deck-board__badge--muted">Read only</span>
					</div>
					<div class="deck-board__subtitle">Drag and drop cards between stacks.</div>
				</div>
				<div class="deck-board__actions">
					<NcButton type="secondary" @click="openBoard">
						<template #icon>
							<OpenInNew :size="18" />
						</template>
						Open in Deck
					</NcButton>
					<NcButton type="secondary" :disabled="loading" @click="reload">
						<template #icon>
							<Refresh :size="18" />
						</template>
						Reload
					</NcButton>
				</div>
			</div>

			<DeckRasciManager
				v-if="!loading && !error && canManage"
				:board-id="boardId"
				:organization-id="normalizedOrganizationId"
				:members="projectMembers"
				:stacks="sortedStacks"
				:can-manage-profiles="canManageProfiles"
			/>

			<div v-if="loading" class="deck-board__muted">Loading board...</div>
			<div v-else-if="error" class="deck-board__muted">{{ error }}</div>
			<div v-else class="deck-board__lane" @dragover.prevent>
				<div v-for="stack in sortedStacks" :key="stack.id" class="deck-board__stack">
					<div class="deck-board__stack-head">
						<div class="deck-board__stack-title">{{ stack.title }}</div>
						<div class="deck-board__stack-count">{{ (stack.cards || []).length }}</div>
					</div>

					<div class="deck-board__cards">
						<div
							v-for="(slot, slotIndex) in dropSlots(stack)"
							:key="`${stack.id}-slot-${slotIndex}`"
							class="deck-board__slot"
							:class="slotClass(stack.id, slotIndex)"
							@dragenter.prevent="setDropTarget(stack.id, slotIndex)"
							@dragover.prevent="setDropTarget(stack.id, slotIndex)"
							@drop.prevent="handleDrop(stack.id, slotIndex)"
						>
							<div v-if="slotIndex < (stack.cards || []).length" class="deck-board__card-wrap">
								<div
									class="deck-board__card"
									:class="{ 'deck-board__card--dragging': isDraggingCard(slot.id) }"
									:draggable="canEdit"
									@dragstart="handleDragStart(slot, stack.id, slotIndex, $event)"
									@dragend="handleDragEnd"
									@click="openCard(stack.id, slot)"
								>
									<div class="deck-board__card-title">{{ slot.title }}</div>
									<div v-if="Array.isArray(slot.labels) && slot.labels.length > 0" class="deck-board__labels">
										<span
											v-for="label in slot.labels.slice(0, 6)"
											:key="label.id"
											class="deck-board__label"
											:style="{ background: `#${label.color}` }"
											:title="label.title"
										/>
									</div>
								</div>
							</div>
						</div>

					<div v-if="canEdit" class="deck-board__add">
						<input
							v-model="newCardTitleByStack[stack.id]"
							type="text"
							class="deck-board__input"
							:placeholder="`Add a card...`"
							@keydown.enter.prevent="createCard(stack)"
						/>
						<NcButton
							type="secondary"
							:disabled="creatingStackId === stack.id || !canCreateForStack(stack)"
							@click="createCard(stack)"
						>
							<template #icon>
								<Plus :size="18" />
							</template>
							Add
						</NcButton>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import { generateUrl } from '@nextcloud/router'

import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'

import { DeckService } from '../../Services/deck.js'
import { ProjectsService } from '../../Services/projects.js'
import DeckRasciManager from './DeckRasciManager.vue'

const deckService = DeckService.getInstance()
const projectsService = ProjectsService.getInstance()

export default {
	name: 'DeckBoard',
	components: {
		DeckRasciManager,
		NcButton,
		OpenInNew,
		Plus,
		Refresh,
	},
	props: {
		boardId: {
			type: [String, Number],
			default: null,
		},
		projectId: {
			type: [String, Number],
			default: null,
		},
		organizationId: {
			type: Number,
			default: null,
		},
		canManageProfiles: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			board: null,
			permissions: null,
			error: '',
			loading: false,
			stacks: [],
			projectMembers: [],
			creatingStackId: null,
			newCardTitleByStack: {},
			drag: {
				active: false,
				cardId: null,
				fromStackId: null,
				fromIndex: null,
			},
			drop: {
				stackId: null,
				index: null,
			},
		}
	},
	computed: {
		boardTitle() {
			return this.board?.title || 'Deck board'
		},
		canEdit() {
			if (this.permissions && typeof this.permissions === 'object') {
				return !!this.permissions.PERMISSION_EDIT
			}
			return !!this.board?.permissions?.PERMISSION_EDIT
		},
		canManage() {
			if (this.permissions && typeof this.permissions === 'object') {
				return !!this.permissions.PERMISSION_MANAGE
			}
			return !!this.board?.permissions?.PERMISSION_MANAGE
		},
		normalizedOrganizationId() {
			const value = Number(this.organizationId)
			return Number.isFinite(value) && value > 0 ? value : null
		},
		sortedStacks() {
			return (this.stacks || []).slice().sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
		},
	},
	watch: {
		boardId: {
			immediate: true,
			handler() {
				this.load()
			},
		},
		projectId() {
			this.loadProjectMembers()
		},
	},
	methods: {
		async load() {
			this.resetState()
			if (!this.boardId) {
				return
			}
			this.loading = true
			try {
				const [board, permissions, stacks, members] = await Promise.all([
					deckService.getBoard(this.boardId),
					deckService.getBoardPermissions(this.boardId),
					deckService.listStacks(this.boardId),
					this.projectId ? projectsService.listMembers(Number(this.projectId)) : Promise.resolve([]),
				])
				this.board = board
				this.permissions = permissions
				this.stacks = this.normalizeStacks(stacks)
				this.projectMembers = Array.isArray(members) ? members : []
			} catch (e) {
				this.error = 'Could not load Deck board.'
			} finally {
				this.loading = false
			}
		},
		async loadProjectMembers() {
			if (!this.projectId) {
				this.projectMembers = []
				return
			}
			try {
				const members = await projectsService.listMembers(Number(this.projectId))
				this.projectMembers = Array.isArray(members) ? members : []
			} catch (e) {
				this.projectMembers = []
			}
		},
		reload() {
			this.load()
		},
		resetState() {
			this.board = null
			this.permissions = null
			this.stacks = []
			this.projectMembers = []
			this.error = ''
			this.creatingStackId = null
			this.drag = { active: false, cardId: null, fromStackId: null, fromIndex: null }
			this.drop = { stackId: null, index: null }
		},
		normalizeStacks(stacks) {
			const list = Array.isArray(stacks) ? stacks : []
			return list.map((stack) => {
				const cards = Array.isArray(stack.cards) ? stack.cards : []
				return {
					...stack,
					cards: cards.slice().sort((a, b) => (a.order ?? 0) - (b.order ?? 0)),
				}
			})
		},
		openBoard() {
			if (!this.boardId) {
				return
			}
			const url = generateUrl(`/apps/deck/#/board/${this.boardId}`)
			window.open(url, '_blank')
		},
		openCard(stackId, card) {
			if (!this.boardId || !card?.id) {
				return
			}
			const url = generateUrl(`/apps/deck/#/board/${this.boardId}/card/${card.id}`)
			window.open(url, '_blank')
		},
		dropSlots(stack) {
			const len = Array.isArray(stack.cards) ? stack.cards.length : 0
			return new Array(len + 1).fill(null).map((_, idx) => (idx < len ? stack.cards[idx] : null))
		},
		slotClass(stackId, index) {
			const active = this.drop.stackId !== null && this.drop.index !== null
			const isTarget = active && String(this.drop.stackId) === String(stackId) && Number(this.drop.index) === Number(index)
			return {
				'deck-board__slot--target': isTarget && this.drag.active,
				'deck-board__slot--disabled': !this.canEdit,
			}
		},
		setDropTarget(stackId, index) {
			if (!this.canEdit || !this.drag.active) {
				return
			}
			this.drop.stackId = stackId
			this.drop.index = index
		},
		handleDragStart(card, fromStackId, fromIndex, evt) {
			if (!this.canEdit) {
				return
			}
			this.drag = {
				active: true,
				cardId: card.id,
				fromStackId,
				fromIndex,
			}
			this.drop = { stackId: fromStackId, index: fromIndex }
			try {
				evt.dataTransfer.effectAllowed = 'move'
				evt.dataTransfer.setData('text/plain', String(card.id))
			} catch (e) {
				// ignore
			}
		},
		handleDragEnd() {
			this.drag = { active: false, cardId: null, fromStackId: null, fromIndex: null }
			this.drop = { stackId: null, index: null }
		},
		isDraggingCard(cardId) {
			return this.drag.active && this.drag.cardId !== null && String(this.drag.cardId) === String(cardId)
		},
		async handleDrop(targetStackId, targetIndex) {
			if (!this.canEdit || !this.drag.active) {
				return
			}

			const cardId = this.drag.cardId
			const fromStackId = this.drag.fromStackId
			const fromIndex = this.drag.fromIndex
			this.handleDragEnd()

			if (cardId === null || fromStackId === null || fromIndex === null) {
				return
			}

			let nextIndex = targetIndex
			if (String(fromStackId) === String(targetStackId) && Number(fromIndex) < Number(targetIndex)) {
				nextIndex = Math.max(0, Number(targetIndex) - 1)
			}

			const snapshot = JSON.parse(JSON.stringify(this.stacks))
			try {
				this.moveCardLocal(cardId, fromStackId, fromIndex, targetStackId, nextIndex)
				await deckService.reorderCard(cardId, targetStackId, nextIndex)
			} catch (e) {
				this.stacks = snapshot
			}
		},
		moveCardLocal(cardId, fromStackId, fromIndex, targetStackId, targetIndex) {
			const fromStack = this.stacks.find((s) => String(s.id) === String(fromStackId))
			const toStack = this.stacks.find((s) => String(s.id) === String(targetStackId))
			if (!fromStack || !toStack) {
				return
			}
			const fromCards = Array.isArray(fromStack.cards) ? fromStack.cards.slice() : []
			const toCards = String(fromStackId) === String(targetStackId) ? fromCards : (Array.isArray(toStack.cards) ? toStack.cards.slice() : [])

			const idx = fromCards.findIndex((c) => String(c.id) === String(cardId))
			if (idx === -1) {
				return
			}
			const [card] = fromCards.splice(idx, 1)
			const insertIndex = Math.max(0, Math.min(targetIndex, toCards.length))
			toCards.splice(insertIndex, 0, { ...card, stackId: targetStackId })

			fromStack.cards = fromCards.map((c, i) => ({ ...c, order: i }))
			if (String(fromStackId) === String(targetStackId)) {
				fromStack.cards = toCards.map((c, i) => ({ ...c, order: i }))
			} else {
				toStack.cards = toCards.map((c, i) => ({ ...c, order: i }))
			}
		},
		canCreateForStack(stack) {
			const title = (this.newCardTitleByStack[stack.id] || '').trim()
			return title.length > 0
		},
		async createCard(stack) {
			if (!this.canEdit || !stack) {
				return
			}
			const title = (this.newCardTitleByStack[stack.id] || '').trim()
			if (title === '') {
				return
			}
			this.creatingStackId = stack.id
			try {
				const order = Array.isArray(stack.cards) ? stack.cards.length : 0
				const card = await deckService.createCard(stack.id, title, order)
				this.newCardTitleByStack = { ...this.newCardTitleByStack, [stack.id]: '' }
				const target = this.stacks.find((s) => String(s.id) === String(stack.id))
				if (target) {
					const nextCards = Array.isArray(target.cards) ? target.cards.slice() : []
					nextCards.push({ ...card, order: nextCards.length })
					target.cards = nextCards
				}
			} catch (e) {
				// ignore
			} finally {
				this.creatingStackId = null
			}
		},
	},
}
</script>

<style scoped>
.deck-board {
	display: grid;
	gap: 12px;
}

.deck-board__empty {
	color: var(--color-text-maxcontrast);
	padding: 10px 2px;
}

.deck-board__top {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: wrap;
}

.deck-board__title {
	display: flex;
	align-items: baseline;
	gap: 8px;
	flex-wrap: wrap;
}

.deck-board__title-text {
	font-size: 16px;
	font-weight: 900;
}

.deck-board__badge {
	font-size: 12px;
	font-weight: 800;
	padding: 3px 10px;
	border-radius: 999px;
	border: 1px solid var(--color-border-dark);
	background: var(--color-main-background);
	color: var(--color-text-maxcontrast);
}

.deck-board__badge--muted {
	opacity: 0.8;
}

.deck-board__subtitle {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	margin-top: 4px;
}

.deck-board__actions {
	display: inline-flex;
	gap: 8px;
	flex-wrap: wrap;
}

.deck-board__muted {
	color: var(--color-text-maxcontrast);
}

.deck-board__lane {
	display: grid;
	grid-auto-flow: column;
	grid-auto-columns: minmax(260px, 320px);
	gap: 12px;
	align-items: stretch;
	overflow-x: auto;
	padding-bottom: 6px;
}

.deck-board__stack {
	border: 1px solid var(--color-border);
	border-radius: 14px;
	background: var(--color-main-background);
	overflow: hidden;
	display: flex;
	flex-direction: column;
	min-height: 430px;
	max-height: min(76vh, 860px);
}

.deck-board__stack-head {
	padding: 12px 12px 10px;
	border-bottom: 1px solid var(--color-border);
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
}

.deck-board__stack-title {
	font-weight: 900;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.deck-board__stack-count {
	font-size: 12px;
	font-weight: 900;
	color: var(--color-text-maxcontrast);
	border: 1px solid var(--color-border-dark);
	border-radius: 999px;
	padding: 2px 9px;
}

.deck-board__cards {
	padding: 10px;
	display: grid;
	gap: 8px;
	min-height: 0;
	max-height: min(62vh, 720px);
	overflow-y: auto;
}

.deck-board__slot {
	border-radius: 12px;
	min-height: 14px;
}

.deck-board__slot--target {
	box-shadow: inset 0 0 0 2px var(--color-primary-element);
	background: rgba(0, 0, 0, 0.03);
}

.deck-board__slot--disabled {
	cursor: default;
}

.deck-board__card {
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 10px 10px 8px;
	background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.75));
	color: var(--color-main-text);
	cursor: pointer;
	box-shadow: 0 1px 0 rgba(0, 0, 0, 0.04);
}

.deck-board__card:hover {
	background: var(--color-background-hover);
}

.deck-board__card:active {
	transform: translateY(1px);
}

.deck-board__card[draggable='true'] {
	cursor: grab;
}

.deck-board__card--dragging {
	opacity: 0.55;
}

.deck-board__card-title {
	font-weight: 800;
	line-height: 1.2;
	word-break: break-word;
}

.deck-board__labels {
	margin-top: 8px;
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}

.deck-board__label {
	width: 14px;
	height: 6px;
	border-radius: 999px;
	box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08);
}

.deck-board__add {
	margin-top: 4px;
	padding: 10px;
	border-top: 1px solid var(--color-border);
	display: flex;
	gap: 8px;
	align-items: center;
}

.deck-board__input {
	flex: 1;
	min-width: 0;
	border: 1px solid var(--color-border-dark);
	border-radius: 10px;
	padding: 8px 10px;
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.deck-board__input:focus {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 1px;
}

@media (max-width: 900px) {
	.deck-board__lane {
		grid-auto-columns: minmax(240px, 86vw);
	}

	.deck-board__stack {
		min-height: 360px;
		max-height: min(70vh, 680px);
	}

	.deck-board__cards {
		max-height: min(56vh, 560px);
	}
}
</style>
