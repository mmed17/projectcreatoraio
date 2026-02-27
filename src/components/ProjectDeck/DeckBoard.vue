<template>
	<div class="deck-board">
		<div v-if="!boardId" class="deck-board__empty">
			No Deck board linked to this project.
		</div>

		<div v-else>
			<div class="deck-board__top">
				<div class="deck-board__meta">
					<div class="deck-board__title">
						<span class="deck-board__title-text">{{ boardTitle }}</span>
						<span class="deck-board__badge">#{{ boardId }}</span>
						<span v-if="!canEdit" class="deck-board__badge deck-board__badge--muted">Read only</span>
					</div>
					<div class="deck-board__subtitle">
						Deck tasks embedded in project workspace.
					</div>
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

			<DeckCardPolicyManager
				v-if="!loading && !error && canManage"
				:board-id="boardId"
				:members="projectMembers" />

			<div v-if="loading" class="deck-board__muted">
				Loading board...
			</div>
			<div v-else-if="error" class="deck-board__muted">
				{{ error }}
			</div>
			<div v-else class="deck-board__embed">
				<div v-if="embeddedError" class="deck-board__muted">
					{{ embeddedError }}
				</div>
				<div v-else-if="!embeddedReady" class="deck-board__muted">
					Loading Deck tasks UI...
				</div>
				<div ref="deckMount" class="deck-board__mount" />
			</div>
		</div>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import { generateFilePath, generateUrl } from '@nextcloud/router'

import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'

import { DeckService } from '../../Services/deck.js'
import { ProjectsService } from '../../Services/projects.js'
import DeckCardPolicyManager from './DeckCardPolicyManager.vue'

const deckService = DeckService.getInstance()
const projectsService = ProjectsService.getInstance()

export default {
	name: 'DeckBoard',
	components: {
		DeckCardPolicyManager,
		NcButton,
		OpenInNew,
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
	},
	data() {
		return {
			board: null,
			permissions: null,
			error: '',
			loading: false,
			projectMembers: [],
			embeddedReady: false,
			embeddedError: '',
			embeddedHandle: null,
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
		deckBoardUrl() {
			if (!this.boardId) {
				return ''
			}
			return generateUrl(`/apps/deck/board/${this.boardId}`)
		},
	},
	watch: {
		boardId: {
			immediate: true,
			async handler() {
				this.unmountEmbedded()
				await this.load()
				await this.mountEmbedded({ forceRemount: true })
			},
		},
		projectId() {
			this.loadProjectMembers()
		},
	},
	beforeDestroy() {
		this.unmountEmbedded()
	},
	methods: {
		async load() {
			this.resetState()
			if (!this.boardId) {
				return
			}
			this.loading = true
			try {
				const [board, permissions, members] = await Promise.all([
					deckService.getBoard(this.boardId),
					deckService.getBoardPermissions(this.boardId),
					this.projectId ? projectsService.listMembers(Number(this.projectId)) : Promise.resolve([]),
				])
				this.board = board
				this.permissions = permissions
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
			this.mountEmbedded({ forceRemount: true })
		},
		resetState() {
			this.board = null
			this.permissions = null
			this.projectMembers = []
			this.error = ''
			this.embeddedReady = false
			this.embeddedError = ''
		},
		openBoard() {
			if (!this.deckBoardUrl) {
				return
			}
			window.open(this.deckBoardUrl, '_blank', 'noopener')
		},
		async ensureEmbeddedApiLoaded() {
			if (window?.OCA?.Deck?.EmbeddedTasks?.mount) {
				return true
			}

			// Must be a static asset URL (no index.php front controller), otherwise Nextcloud returns HTML.
			const src = generateFilePath('deck', '', 'js/deck-embedded-tasks.js')
			window.__projectcreatoraioDeckEmbedLoading = window.__projectcreatoraioDeckEmbedLoading || new Promise((resolve, reject) => {
				const existing = document.querySelector(`script[data-projectcreatoraio-deck-embed="1"][src="${src}"]`)
				if (existing) {
					existing.addEventListener('load', resolve, { once: true })
					existing.addEventListener('error', reject, { once: true })
					return
				}

				const script = document.createElement('script')
				script.dataset.projectcreatoraioDeckEmbed = '1'
				script.src = src
				script.async = true
				script.addEventListener('load', resolve, { once: true })
				script.addEventListener('error', reject, { once: true })
				document.head.appendChild(script)
			})

			try {
				await window.__projectcreatoraioDeckEmbedLoading
			} catch (e) {
				return false
			}

			return !!window?.OCA?.Deck?.EmbeddedTasks?.mount
		},
		unmountEmbedded() {
			if (this.embeddedHandle && typeof this.embeddedHandle.destroy === 'function') {
				try {
					this.embeddedHandle.destroy()
				} catch (e) {
					// ignore
				}
			}
			this.embeddedHandle = null
			this.embeddedReady = false
		},
		async mountEmbedded({ forceRemount = false } = {}) {
			this.embeddedError = ''
			this.embeddedReady = false

			const boardId = Number(this.boardId)
			if (!Number.isFinite(boardId) || boardId <= 0) {
				this.unmountEmbedded()
				return
			}

			const ok = await this.ensureEmbeddedApiLoaded()
			if (!ok) {
				this.unmountEmbedded()
				this.embeddedError = 'Deck embedded tasks UI is not available. Build and deploy the Deck app update, then reload.'
				return
			}

			if (this.embeddedHandle && !forceRemount && typeof this.embeddedHandle.setBoardId === 'function') {
				this.embeddedHandle.setBoardId(boardId)
				this.embeddedReady = true
				return
			}

			this.unmountEmbedded()

			const el = this.$refs.deckMount
			if (!el) {
				this.embeddedError = 'Could not mount Deck tasks UI.'
				return
			}
			el.innerHTML = ''

			try {
				this.embeddedHandle = window.OCA.Deck.EmbeddedTasks.mount({ el, boardId })
				this.embeddedReady = true
			} catch (e) {
				this.unmountEmbedded()
				this.embeddedError = 'Could not mount Deck tasks UI.'
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

.deck-board__embed {
	display: grid;
	gap: 8px;
}

.deck-board__mount {
	width: 100%;
	min-height: min(78vh, 1000px);
	height: min(78vh, 1000px);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
	overflow: hidden;
}

@media (max-width: 900px) {
	.deck-board__mount {
		min-height: min(74vh, 880px);
		height: min(74vh, 880px);
	}
}
</style>
