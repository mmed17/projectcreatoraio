<template>
	<div>
		<button
			class="folder-tree-item"
			:class="{ 'folder-tree-item--selected': isSelected }"
			:type="'button'"
			:style="rowStyle"
			@click="$emit('select', node)"
		>
			<span v-if="hasChildren" class="folder-tree-item__chev" @click.stop="$emit('toggle', node)">
				<ChevronDown v-if="isExpanded" :size="18" />
				<ChevronRight v-else :size="18" />
			</span>
			<span v-else class="folder-tree-item__chev folder-tree-item__chev--empty" />
			<FolderOutline class="folder-tree-item__icon" :size="18" />
			<span class="folder-tree-item__name">{{ node.name }}</span>
			<span v-if="fileCount !== null" class="folder-tree-item__meta">{{ fileCount }} files</span>
		</button>

		<div v-if="isExpanded" class="folder-tree-item__children">
			<FolderTreeItem
				v-for="child in childFolders"
				:key="child.id"
				:node="child"
				:depth="depth + 1"
				:expanded-ids="expandedIds"
				:selected-id="selectedId"
				@toggle="$emit('toggle', $event)"
				@select="$emit('select', $event)"
			/>
		</div>
	</div>
</template>

<script>
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'

export default {
	name: 'FolderTreeItem',
	components: {
		ChevronDown,
		ChevronRight,
		FolderOutline,
	},
	props: {
		node: {
			type: Object,
			required: true,
		},
		depth: {
			type: Number,
			default: 0,
		},
		expandedIds: {
			type: Array,
			default: () => [],
		},
		selectedId: {
			type: [Number, String],
			default: null,
		},
		showCounts: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		isSelected() {
			return this.selectedId !== null && String(this.selectedId) === String(this.node.id)
		},
		isExpanded() {
			if (!this.hasChildren) {
				return false
			}
			return this.expandedIds.some((id) => String(id) === String(this.node.id))
		},
		hasChildren() {
			return Array.isArray(this.childFolders) && this.childFolders.length > 0
		},
		childFolders() {
			const children = Array.isArray(this.node.children) ? this.node.children : []
			return children
				.filter((child) => child && child.type === 'folder')
				.slice()
				.sort((a, b) => (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base' }))
		},
		rowStyle() {
			return {
				paddingLeft: `${12 + this.depth * 14}px`,
			}
		},
		fileCount() {
			if (!this.showCounts) {
				return null
			}
			return this.countFiles(this.node)
		},
	},
	methods: {
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
	},
}
</script>

<style scoped>
.folder-tree-item {
	width: 100%;
	border: 0;
	background: transparent;
	color: var(--color-main-text);
	text-align: left;
	display: grid;
	grid-template-columns: 20px 20px 1fr auto;
	gap: 8px;
	align-items: center;
	padding: 8px 10px;
	border-radius: 8px;
	cursor: pointer;
}

.folder-tree-item:hover {
	background: var(--color-background-hover);
}

.folder-tree-item--selected {
	background: var(--color-background-hover);
	outline: 1px solid var(--color-border-dark);
}

.folder-tree-item__chev {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 20px;
	height: 20px;
	border-radius: 6px;
}

.folder-tree-item__chev:hover {
	background: rgba(127, 127, 127, 0.12);
}

.folder-tree-item__chev--empty:hover {
	background: transparent;
}

.folder-tree-item__icon {
	color: var(--color-text-maxcontrast);
}

.folder-tree-item__name {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.folder-tree-item__meta {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 600;
	padding-left: 8px;
}

.folder-tree-item__children {
	margin-top: 2px;
}
</style>
