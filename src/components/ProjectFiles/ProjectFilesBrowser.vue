<template>
	<div class="project-files">
		<div class="project-files__header">
			<div class="project-files__tabs">
				<button
					:type="'button'"
					class="project-files__tab"
					:class="{ 'project-files__tab--active': scope === 'shared' }"
					@click="setScope('shared')"
				>
					Shared
					<span class="project-files__tab-pill">{{ sharedFileCount }}</span>
				</button>
				<button
					:type="'button'"
					class="project-files__tab"
					:class="{ 'project-files__tab--active': scope === 'private' }"
					@click="setScope('private')"
				>
					Private
					<span class="project-files__tab-pill">{{ privateFileCount }}</span>
				</button>
			</div>

			<div class="project-files__tools">
				<NcTextField v-model="search" label="Search files" input-label="Search files" placeholder="Search names">
					<template #icon>
						<Magnify :size="18" />
					</template>
				</NcTextField>
				<button v-if="search.trim().length > 0" :type="'button'" class="project-files__clear" @click="clearSearch">
					Clear
				</button>
			</div>
		</div>

		<div v-if="loading" class="project-files__muted">Loading file structure...</div>
		<div v-else-if="error" class="project-files__muted">{{ error }}</div>
		<div v-else class="project-files__grid">
			<div class="project-files__pane project-files__pane--left">
				<div class="project-files__pane-title">Folders</div>
				<div v-if="activeRoots.length === 0" class="project-files__empty">No folders found.</div>
				<div v-else class="project-files__tree">
					<FolderTreeItem
						v-for="root in folderRoots"
						:key="`${scope}-root-${root.id}`"
						:node="root"
						:depth="0"
						:expanded-ids="expandedIds"
						:selected-id="selectedFolderId"
						:show-counts="false"
						@toggle="toggleFolder"
						@select="selectFolder"
					/>
				</div>
			</div>

			<div class="project-files__pane project-files__pane--right">
				<div class="project-files__right-top">
					<div class="project-files__breadcrumbs">
						<button
							v-for="(crumb, idx) in selectedChain"
							:key="`crumb-${crumb.id}`"
							:type="'button'"
							class="project-files__crumb"
							@click="selectFolder(crumb)"
						>
							{{ crumb.name }}
						</button>
						<span v-if="selectedChain.length === 0" class="project-files__muted">No folder selected</span>
					</div>

					<div class="project-files__actions">
						<button
							:type="'button'"
							class="project-files__action"
							:disabled="!selectedFolderNode"
							@click="openNodeInFiles(selectedFolderNode)"
						>
							<OpenInNew :size="18" />
							Open
						</button>
						<button
							:type="'button'"
							class="project-files__action"
							:disabled="!selectedFolderNode"
							@click="downloadFolderZip(selectedFolderNode)"
						>
							<Download :size="18" />
							ZIP
						</button>
					</div>
				</div>

				<div class="project-files__list">
					<div v-if="showSearch" class="project-files__results">
						<div class="project-files__pane-title">Search results</div>
						<div v-if="searchResults.length === 0" class="project-files__empty">No matches.</div>
						<ul v-else class="project-files__rows">
							<li v-for="hit in searchResults" :key="`hit-${hit.node.id}`" class="project-files__row">
								<button
									:type="'button'"
									class="project-files__row-main"
									@click="activateSearchHit(hit)"
								>
									<FolderOutline v-if="hit.node.type === 'folder'" :size="18" class="project-files__row-icon" />
									<FileOutline v-else :size="18" class="project-files__row-icon" />
									<span class="project-files__row-name">{{ hit.node.name }}</span>
									<span class="project-files__row-sub">{{ hit.pathLabel || 'Root' }}</span>
								</button>
							</li>
						</ul>
					</div>

					<div v-else>
						<div class="project-files__pane-title">Contents</div>
						<div v-if="!selectedFolderNode" class="project-files__empty">Select a folder to view its contents.</div>
						<div v-else-if="sortedEntries.length === 0" class="project-files__empty">This folder is empty.</div>
						<ul v-else class="project-files__rows">
							<li
								v-for="entry in sortedEntries"
								:key="`entry-${entry.id}`"
								class="project-files__row"
								:class="{ 'project-files__row--highlight': highlightedNodeId !== null && String(highlightedNodeId) === String(entry.id) }"
							>
								<button
									:type="'button'"
									class="project-files__row-main"
									@click="entry.type === 'folder' ? selectFolder(entry) : openFileInFiles(entry)"
								>
									<FolderOutline v-if="entry.type === 'folder'" :size="18" class="project-files__row-icon" />
									<FileOutline v-else :size="18" class="project-files__row-icon" />
									<span class="project-files__row-name">{{ entry.name }}</span>
									<span class="project-files__row-sub">
										<span v-if="entry.type === 'folder'">{{ countFiles(entry) }} files</span>
										<span v-else>{{ formatBytes(entry.size) }}</span>
									</span>
								</button>

								<div class="project-files__row-actions">
									<button
										:type="'button'"
										class="project-files__mini"
										@click.stop="openNodeInFiles(entry)"
									>
										<OpenInNew :size="18" />
									</button>
									<button
										v-if="entry.type === 'file'"
										:type="'button'"
										class="project-files__mini"
										@click.stop="downloadFile(entry)"
									>
										<Download :size="18" />
									</button>
								</div>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
import { createClient } from 'webdav'

import Download from 'vue-material-design-icons/Download.vue'
import FileOutline from 'vue-material-design-icons/FileOutline.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'

import FolderTreeItem from './FolderTreeItem.vue'

const webdavClient = createClient(generateRemoteUrl('dav'))

export default {
	name: 'ProjectFilesBrowser',
	components: {
		Download,
		FileOutline,
		FolderOutline,
		FolderTreeItem,
		Magnify,
		NcTextField,
		OpenInNew,
	},
	props: {
		sharedRoots: {
			type: Array,
			default: () => [],
		},
		privateRoots: {
			type: Array,
			default: () => [],
		},
		loading: {
			type: Boolean,
			default: false,
		},
		error: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
			scope: 'shared',
			search: '',
			highlightedNodeId: null,
			selectedFolderIdByScope: {
				shared: null,
				private: null,
			},
			expandedIdsByScope: {
				shared: [],
				private: [],
			},
		}
	},
	computed: {
		activeRoots() {
			return this.scope === 'shared' ? (this.sharedRoots || []) : (this.privateRoots || [])
		},
		folderRoots() {
			return (this.activeRoots || []).filter((node) => node && node.type === 'folder')
		},
		expandedIds() {
			return this.expandedIdsByScope[this.scope] || []
		},
		selectedFolderId() {
			return this.selectedFolderIdByScope[this.scope]
		},
		selectedChain() {
			if (this.selectedFolderId === null) {
				return []
			}
			return this.findChainById(this.activeRoots, this.selectedFolderId)
		},
		selectedFolderNode() {
			if (this.selectedChain.length === 0) {
				return null
			}
			return this.selectedChain[this.selectedChain.length - 1]
		},
		sortedEntries() {
			const children = Array.isArray(this.selectedFolderNode?.children) ? this.selectedFolderNode.children : []
			return children.slice().sort((a, b) => {
				const aFolder = a.type === 'folder'
				const bFolder = b.type === 'folder'
				if (aFolder !== bFolder) {
					return aFolder ? -1 : 1
				}
				return (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base' })
			})
		},
		showSearch() {
			return this.search.trim().length > 0
		},
		searchResults() {
			const q = this.search.trim().toLowerCase()
			if (q === '') {
				return []
			}

			const hits = []
			this.walkWithChain(this.activeRoots, [], (node, chain) => {
				const name = (node?.name || '').toLowerCase()
				if (!name.includes(q)) {
					return
				}
				const pathLabel = chain.slice(0, -1).map((n) => n.name).join(' / ')
				hits.push({ node, chain, pathLabel })
			})

			return hits.slice(0, 50)
		},
		sharedFileCount() {
			return this.countFilesInRoots(this.sharedRoots)
		},
		privateFileCount() {
			return this.countFilesInRoots(this.privateRoots)
		},
	},
	watch: {
		sharedRoots() {
			this.ensureSelection('shared')
		},
		privateRoots() {
			this.ensureSelection('private')
		},
	},
	mounted() {
		this.ensureSelection('shared')
		this.ensureSelection('private')
	},
	methods: {
		setScope(scope) {
			this.scope = scope
			this.highlightedNodeId = null
			this.ensureSelection(scope)
		},
		ensureSelection(scope) {
			const roots = scope === 'shared' ? (this.sharedRoots || []) : (this.privateRoots || [])
			if (!Array.isArray(roots) || roots.length === 0) {
				this.selectedFolderIdByScope[scope] = null
				this.expandedIdsByScope[scope] = []
				return
			}

			const currentId = this.selectedFolderIdByScope[scope]
			const hasSelection = currentId !== null && this.findChainById(roots, currentId).length > 0
			if (currentId === null || !hasSelection) {
				this.selectedFolderIdByScope[scope] = roots[0].id
			}

			if (!this.expandedIdsByScope[scope].some((id) => String(id) === String(roots[0].id))) {
				this.expandedIdsByScope[scope] = [roots[0].id, ...this.expandedIdsByScope[scope]]
			}
		},
		clearSearch() {
			this.search = ''
		},
		toggleFolder(node) {
			if (!node || node.type !== 'folder') {
				return
			}
			const list = this.expandedIdsByScope[this.scope] || []
			const exists = list.some((id) => String(id) === String(node.id))
			this.expandedIdsByScope[this.scope] = exists
				? list.filter((id) => String(id) !== String(node.id))
				: [...list, node.id]
		},
		selectFolder(node) {
			if (!node || node.type !== 'folder') {
				return
			}
			this.selectedFolderIdByScope[this.scope] = node.id
			const chain = this.findChainById(this.activeRoots, node.id)
			const expanded = new Set(this.expandedIdsByScope[this.scope] || [])
			for (const crumb of chain) {
				if (crumb && crumb.type === 'folder') {
					expanded.add(crumb.id)
				}
			}
			this.expandedIdsByScope[this.scope] = Array.from(expanded)
			this.highlightedNodeId = null
		},
		activateSearchHit(hit) {
			if (!hit || !hit.node) {
				return
			}
			if (hit.node.type === 'folder') {
				this.search = ''
				this.selectFolder(hit.node)
				return
			}

			const chain = Array.isArray(hit.chain) ? hit.chain : []
			const parent = chain.length >= 2 ? chain[chain.length - 2] : null
			if (parent && parent.type === 'folder') {
				this.search = ''
				this.selectFolder(parent)
				this.highlightedNodeId = hit.node.id
			}
		},
		openNodeInFiles(node) {
			if (!node || !node.path) {
				return
			}
			if (node.type === 'folder') {
				const dir = this.dirFromNodePath(node.path)
				const url = generateUrl(`/apps/files/?dir=${encodeURIComponent(dir)}`)
				window.open(url, '_blank')
				return
			}

			this.openFileInFiles(node)
		},
		openFileInFiles(fileNode) {
			if (!fileNode) {
				return
			}

			const chain = this.findChainById(this.activeRoots, fileNode.id)
			const parent = chain.length >= 2 ? chain[chain.length - 2] : null
			const dir = parent?.path ? this.dirFromNodePath(parent.path) : (fileNode.path ? this.dirFromNodePath(fileNode.path, true) : '/')
			const url = generateUrl(`/apps/files/?dir=${encodeURIComponent(dir)}&openfile=${encodeURIComponent(fileNode.id)}`)
			window.open(url, '_blank')
		},
		downloadFile(fileNode) {
			if (!fileNode?.path) {
				return
			}
			const davPath = this.normalizedDavPath(fileNode.path)
			const href = webdavClient.getFileDownloadLink(davPath)
			this.triggerDownload(href)
		},
		downloadFolderZip(folderNode) {
			if (!folderNode?.path) {
				return
			}
			const davPath = this.normalizedDavPath(folderNode.path)
			const url = new URL(webdavClient.getFileDownloadLink(davPath))
			url.searchParams.append('accept', 'zip')
			this.triggerDownload(url.href)
		},
		triggerDownload(href) {
			const link = document.createElement('a')
			link.href = href
			link.style.display = 'none'
			document.body.appendChild(link)
			link.click()
			link.remove()
		},
		dirFromNodePath(path, parent = false) {
			if (!path || typeof path !== 'string') {
				return '/'
			}
			const marker = '/files'
			const idx = path.indexOf(marker)
			let dir = idx >= 0 ? path.slice(idx + marker.length) : path
			if (!dir.startsWith('/')) {
				dir = `/${dir}`
			}
			if (parent) {
				const parts = dir.split('/').filter(Boolean)
				parts.pop()
				dir = '/' + parts.join('/')
				if (dir === '') {
					dir = '/'
				}
			}
			return dir
		},
		normalizedDavPath(path) {
			const parts = String(path).split('/')
			if (parts.length >= 3) {
				;[parts[1], parts[2]] = [parts[2], parts[1]]
			}
			return parts.join('/')
		},
		formatBytes(bytes) {
			const value = typeof bytes === 'number' ? bytes : Number(bytes)
			if (!Number.isFinite(value) || value <= 0) {
				return '0 B'
			}
			const units = ['B', 'KB', 'MB', 'GB', 'TB']
			let v = value
			let i = 0
			while (v >= 1024 && i < units.length - 1) {
				v /= 1024
				i += 1
			}
			const digits = i === 0 ? 0 : (v < 10 ? 1 : 0)
			return `${v.toFixed(digits)} ${units[i]}`
		},
		countFiles(node) {
			if (!node) {
				return 0
			}
			if (node.type === 'file') {
				return 1
			}
			const children = Array.isArray(node.children) ? node.children : []
			let count = 0
			for (const child of children) {
				count += this.countFiles(child)
			}
			return count
		},
		countFilesInRoots(roots) {
			const list = Array.isArray(roots) ? roots : []
			let count = 0
			for (const node of list) {
				count += this.countFiles(node)
			}
			return count
		},
		walkWithChain(nodes, chain, cb) {
			const list = Array.isArray(nodes) ? nodes : []
			for (const node of list) {
				if (!node) {
					continue
				}
				const nextChain = [...chain, node]
				cb(node, nextChain)
				if (Array.isArray(node.children) && node.children.length > 0) {
					this.walkWithChain(node.children, nextChain, cb)
				}
			}
		},
		findChainById(nodes, targetId) {
			const list = Array.isArray(nodes) ? nodes : []
			for (const node of list) {
				if (!node) {
					continue
				}
				if (String(node.id) === String(targetId)) {
					return [node]
				}
				if (Array.isArray(node.children) && node.children.length > 0) {
					const childChain = this.findChainById(node.children, targetId)
					if (childChain.length > 0) {
						return [node, ...childChain]
					}
				}
			}
			return []
		},
	},
}
</script>

<style scoped>
.project-files {
	display: grid;
	gap: 12px;
}

.project-files__header {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	align-items: center;
	justify-content: space-between;
}

.project-files__tabs {
	display: inline-flex;
	border: 1px solid var(--color-border-dark);
	border-radius: 999px;
	overflow: hidden;
}

.project-files__tab {
	border: 0;
	background: transparent;
	padding: 8px 12px;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
	font-weight: 700;
	display: inline-flex;
	align-items: center;
	gap: 8px;
}

.project-files__tab--active {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}

.project-files__tab-pill {
	min-width: 28px;
	padding: 2px 8px;
	border-radius: 999px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.project-files__tools {
	flex: 1;
	min-width: 260px;
	max-width: 620px;
	display: flex;
	gap: 10px;
	align-items: flex-end;
}

.project-files__clear {
	border: 1px solid var(--color-border-dark);
	background: transparent;
	color: var(--color-main-text);
	border-radius: 10px;
	padding: 8px 12px;
	cursor: pointer;
	font-weight: 700;
}

.project-files__clear:hover {
	background: var(--color-background-hover);
}

.project-files__muted {
	color: var(--color-text-maxcontrast);
}

.project-files__grid {
	display: grid;
	grid-template-columns: minmax(240px, 320px) minmax(0, 1fr);
	gap: 12px;
	min-height: 380px;
}

.project-files__pane {
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
	min-height: 0;
	display: flex;
	flex-direction: column;
}

.project-files__pane--left {
	overflow: hidden;
}

.project-files__pane-title {
	padding: 12px 14px;
	font-size: 12px;
	text-transform: uppercase;
	letter-spacing: 0.06em;
	color: var(--color-text-maxcontrast);
	border-bottom: 1px solid var(--color-border);
}

.project-files__tree {
	padding: 10px;
	overflow: auto;
	min-height: 0;
}

.project-files__right-top {
	padding: 10px 12px;
	border-bottom: 1px solid var(--color-border);
	display: flex;
	gap: 10px;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
}

.project-files__breadcrumbs {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	align-items: center;
	min-width: 0;
}

.project-files__crumb {
	border: 1px solid var(--color-border-dark);
	background: transparent;
	color: var(--color-main-text);
	border-radius: 999px;
	padding: 4px 10px;
	cursor: pointer;
	font-size: 13px;
	max-width: 240px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.project-files__crumb:hover {
	background: var(--color-background-hover);
}

.project-files__actions {
	display: inline-flex;
	gap: 8px;
}

.project-files__action {
	border: 1px solid var(--color-border-dark);
	background: transparent;
	color: var(--color-main-text);
	border-radius: 10px;
	padding: 6px 10px;
	cursor: pointer;
	font-weight: 700;
	font-size: 13px;
	display: inline-flex;
	align-items: center;
	gap: 6px;
}

.project-files__action:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.project-files__action:not(:disabled):hover {
	background: var(--color-background-hover);
}

.project-files__list {
	padding: 0;
	min-height: 0;
	overflow: auto;
}

.project-files__rows {
	list-style: none;
	margin: 0;
	padding: 10px;
	display: grid;
	gap: 6px;
}

.project-files__row {
	display: flex;
	gap: 10px;
	align-items: center;
	border: 1px solid var(--color-border);
	border-radius: 10px;
	background: var(--color-main-background);
}

.project-files__row--highlight {
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.05);
}

.project-files__row-main {
	flex: 1;
	min-width: 0;
	border: 0;
	background: transparent;
	padding: 10px 12px;
	cursor: pointer;
	color: var(--color-main-text);
	display: grid;
	grid-template-columns: 20px 1fr auto;
	gap: 10px;
	align-items: center;
	text-align: left;
}

.project-files__row-main:hover {
	background: var(--color-background-hover);
	border-radius: 10px;
}

.project-files__row-icon {
	color: var(--color-text-maxcontrast);
}

.project-files__row-name {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-weight: 700;
}

.project-files__row-sub {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	white-space: nowrap;
	padding-left: 12px;
}

.project-files__row-actions {
	display: inline-flex;
	gap: 6px;
	padding-right: 10px;
}

.project-files__mini {
	border: 1px solid var(--color-border-dark);
	background: transparent;
	color: var(--color-main-text);
	border-radius: 10px;
	padding: 6px;
	cursor: pointer;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.project-files__mini:hover {
	background: var(--color-background-hover);
}

.project-files__empty {
	padding: 16px;
	color: var(--color-text-maxcontrast);
}

@media (max-width: 900px) {
	.project-files__grid {
		grid-template-columns: 1fr;
		min-height: auto;
	}
	.project-files__tools {
		min-width: 100%;
		max-width: none;
	}
}
</style>
