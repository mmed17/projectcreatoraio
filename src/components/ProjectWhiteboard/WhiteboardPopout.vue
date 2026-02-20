<template>
	<div class="whiteboard-popout">
		<div class="whiteboard-popout__top">
			<div class="whiteboard-popout__title">
				Whiteboard
			</div>
			<div class="whiteboard-popout__actions">
				<NcButton type="tertiary" @click="closeWindow">
					Close window
				</NcButton>
			</div>
		</div>

		<div v-if="error" class="whiteboard-popout__state">
			{{ error }}
		</div>
		<div v-else-if="loading" class="whiteboard-popout__state">
			<NcLoadingIcon :size="32" />
			<span>Loading...</span>
		</div>
		<div v-else class="whiteboard-popout__content">
			<WhiteboardBoard
				ref="board"
				:project-id="projectId"
				:user-id="userId" />
		</div>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import WhiteboardBoard from './WhiteboardBoard.vue'
import { ProjectsService } from '../../Services/projects'

const projectsService = ProjectsService.getInstance()

export default {
	name: 'WhiteboardPopout',
	components: {
		NcButton,
		NcLoadingIcon,
		WhiteboardBoard,
	},
	props: {
		projectId: {
			type: [String, Number],
			required: true,
		},
	},
	data() {
		return {
			loading: true,
			error: '',
			userId: '',
		}
	},
	async mounted() {
		window.addEventListener('beforeunload', this.handleBeforeUnload)
		document.addEventListener('visibilitychange', this.handleVisibilityChange)

		await this.loadContext()
		await this.openEditor()
	},
	beforeDestroy() {
		window.removeEventListener('beforeunload', this.handleBeforeUnload)
		document.removeEventListener('visibilitychange', this.handleVisibilityChange)
	},
	methods: {
		async loadContext() {
			this.loading = true
			this.error = ''
			try {
				const ctx = await projectsService.context()
				this.userId = String(ctx?.userId || '')
				if (!this.userId) {
					this.error = 'Could not resolve current user.'
				}
			} catch (e) {
				this.error = 'Failed to load context.'
			} finally {
				this.loading = false
			}
		},
		async openEditor() {
			if (!this.userId) {
				return
			}
			await this.$nextTick()
			const board = this.$refs.board
			if (board && typeof board.openOverlay === 'function') {
				board.openOverlay()
			}
		},
		closeWindow() {
			try {
				window.close()
			} catch (e) {
				// ignore
			}
		},
		handleBeforeUnload() {
			try {
				const board = this.$refs.board
				if (board && typeof board.syncLocalSnapshotToServer === 'function') {
					board.syncLocalSnapshotToServer({ force: true })
				}
			} catch (e) {
				// ignore
			}
		},
		handleVisibilityChange() {
			if (document.visibilityState !== 'hidden') {
				return
			}
			try {
				const board = this.$refs.board
				if (board && typeof board.syncLocalSnapshotToServer === 'function') {
					board.syncLocalSnapshotToServer({ force: false })
				}
			} catch (e) {
				// ignore
			}
		},
	},
}
</script>

<style scoped>
.whiteboard-popout {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-height: calc(100vh - 16px);
	padding: 8px;
	box-sizing: border-box;
}

.whiteboard-popout__top {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.whiteboard-popout__title {
	font-size: 14px;
	font-weight: 700;
	color: var(--color-text-maxcontrast);
}

.whiteboard-popout__state {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 10px;
	padding: 24px 12px;
	color: var(--color-text-maxcontrast);
}

.whiteboard-popout__content {
	flex: 1;
	min-height: 0;
}
</style>
