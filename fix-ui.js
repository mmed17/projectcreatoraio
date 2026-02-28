const fs = require('fs');
const content = fs.readFileSync('src/components/ProjectDeck/DeckCardPolicyManager.vue', 'utf8');

const updated = content.replace(/<style scoped>[\s\S]*<\/style>/, `<style scoped>
/* Nextcloud native styling variables & layout */
.pc-deck-policy {
	--pc-border: var(--color-border);
	--pc-bg: var(--color-main-background);
	--pc-bg-hover: var(--color-background-hover);
	--pc-bg-dark: var(--color-background-dark);
	--pc-text: var(--color-main-text);
	--pc-text-muted: var(--color-text-maxcontrast);
	--pc-primary: var(--color-primary-element);
	--pc-primary-text: var(--color-primary-text);
	--pc-radius: var(--border-radius-large, 8px);
	
	display: flex;
	flex-direction: column;
	gap: 24px;
	height: calc(100vh - 150px);
	max-height: 800px;
}

.pc-deck-policy__header h3 {
	margin: 0 0 8px 0;
	font-size: 20px;
	font-weight: bold;
}
.muted { color: var(--pc-text-muted); margin: 0; }
.muted-small { color: var(--pc-text-muted); font-size: 13px; margin: 0 0 8px 0; }
.error { color: var(--color-error); }

.pc-state-message, .pc-enable-state {
	padding: 48px;
	text-align: center;
	color: var(--pc-text-muted);
	background: var(--pc-bg);
	border: 1px dashed var(--pc-border);
	border-radius: var(--pc-radius);
}
.pc-enable-state p { margin-bottom: 24px; }

/* Section Base */
.pc-roles-section {
	background: var(--pc-bg);
	border: 1px solid var(--pc-border);
	border-radius: var(--pc-radius);
	padding: 24px;
}
.pc-section-header { margin-bottom: 24px; }
.pc-section-header h4 { margin: 0 0 8px 0; font-size: 16px; font-weight: 600; }

/* Chips */
.pc-role-legend {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-bottom: 24px;
}
.pc-chip {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 4px 12px;
	border-radius: 999px;
	border: 1px solid var(--pc-border);
	font-size: 13px;
	font-weight: 500;
	background: var(--pc-bg-hover);
}
.pc-dot {
	width: 10px;
	height: 10px;
	border-radius: 50%;
}

/* Members Add */
.pc-member-add-row {
	display: flex;
	gap: 16px;
	align-items: center;
	margin-bottom: 24px;
}
.pc-flex-2 { flex: 2; min-width: 200px; }
.pc-flex-none { flex: 0 0 auto; }

/* Member List */
.pc-member-list {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	gap: 16px;
}
.pc-member-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 12px 16px;
	border: 1px solid var(--pc-border);
	border-radius: var(--pc-radius);
	background: var(--pc-bg);
	box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.pc-member-info { flex: 1; min-width: 0; font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pc-member-role { flex: 0 0 auto; margin: 0 16px; }
.pc-icon-btn {
	background: transparent;
	border: none;
	cursor: pointer;
	font-size: 20px;
	color: var(--pc-text-muted);
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 50%;
	width: 32px;
	height: 32px;
	transition: background-color 0.2s, color 0.2s;
}
.pc-icon-btn:hover { background: var(--pc-bg-dark); }
.pc-icon-btn--danger:hover { color: var(--color-error); background: rgba(227, 27, 35, 0.1); }

/* --- SPLIT PANE (AppNavigation / AppContent style) --- */
.pc-policy-layout {
	display: flex;
	flex-direction: column;
	gap: 24px;
	height: 100%;
}

.pc-split-pane {
	display: flex;
	flex: 1;
	border: 1px solid var(--pc-border);
	border-radius: var(--pc-radius);
	background: var(--pc-bg);
	overflow: hidden;
	box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

/* LEFT: List (AppNavigation style) */
.pc-split-left {
	width: 380px;
	border-right: 1px solid var(--pc-border);
	display: flex;
	flex-direction: column;
	background: var(--pc-bg-hover);
	flex-shrink: 0;
	z-index: 2;
}
.pc-split-header {
	padding: 20px 24px;
	border-bottom: 1px solid var(--pc-border);
	display: flex;
	align-items: center;
	justify-content: space-between;
	background: var(--pc-bg);
}
.pc-split-header h4 { margin: 0; font-size: 16px; font-weight: 600; }
.pc-count-badge {
	background: var(--pc-border);
	padding: 2px 10px;
	border-radius: 999px;
	font-size: 12px;
	font-weight: 600;
	color: var(--pc-text);
}
.pc-split-filters {
	padding: 16px 24px;
	display: flex;
	flex-direction: column;
	gap: 12px;
	border-bottom: 1px solid var(--pc-border);
	background: var(--pc-bg);
}
.pc-search-input, .pc-stack-select {
	width: 100%;
	padding: 10px 14px;
	border: 1px solid var(--pc-border);
	border-radius: var(--border-radius, 4px);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 14px;
	transition: border-color 0.2s;
}
.pc-search-input:focus, .pc-stack-select:focus {
	border-color: var(--pc-primary);
	outline: none;
}

.pc-list-actions {
	padding: 12px 24px;
	background: var(--pc-bg-hover);
	border-bottom: 1px solid var(--pc-border);
	font-size: 14px;
}
.pc-checkbox-label {
	display: inline-flex;
	align-items: center;
	gap: 12px;
	cursor: pointer;
	user-select: none;
	font-weight: 500;
}
.pc-checkbox-label input[type="checkbox"], .pc-card-checkbox input[type="checkbox"] {
	width: 18px;
	height: 18px;
	cursor: pointer;
	accent-color: var(--pc-primary);
}

.pc-card-list {
	flex: 1;
	overflow-y: auto;
	background: var(--pc-bg);
}
.pc-empty-state {
	padding: 48px 24px;
	text-align: center;
	color: var(--pc-text-muted);
	font-size: 14px;
}
.pc-card-item {
	display: flex;
	align-items: flex-start;
	padding: 16px 24px;
	border-bottom: 1px solid var(--pc-border);
	cursor: pointer;
	transition: background 0.15s ease, padding-left 0.15s ease;
}
.pc-card-item:hover { background: var(--pc-bg-hover); }
.pc-card-item.is-selected { 
	background: rgba(0, 130, 201, 0.05);
	border-left: 3px solid var(--pc-primary);
	padding-left: 21px; /* 24px - 3px border */
}
.pc-card-checkbox {
	padding-top: 2px;
	margin-right: 16px;
}
.pc-card-content { flex: 1; min-width: 0; }
.pc-card-title {
	font-weight: 600;
	font-size: 15px;
	margin-bottom: 6px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	color: var(--pc-text);
}
.pc-card-meta {
	display: flex;
	align-items: center;
	gap: 12px;
}
.pc-stack-name {
	font-size: 13px;
	color: var(--pc-text-muted);
	display: flex;
	align-items: center;
	gap: 4px;
}
.pc-stack-name::before {
	content: "•";
	color: var(--pc-border);
}
.pc-badge {
	font-size: 11px;
	padding: 2px 8px;
	border-radius: 4px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
.pc-badge--custom { background: var(--pc-primary); color: var(--pc-primary-text); }
.pc-badge--default { background: var(--pc-bg-dark); color: var(--pc-text-muted); }

/* RIGHT: Inspector (AppContent style) */
.pc-split-right {
	flex: 1;
	display: flex;
	flex-direction: column;
	background: var(--pc-bg);
	min-width: 400px;
	position: relative;
}
.pc-inspector-panel {
	display: flex;
	flex-direction: column;
	height: 100%;
}
.pc-inspector-header {
	padding: 32px 40px 24px;
	border-bottom: 1px solid var(--pc-border);
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	background: var(--pc-bg);
}
.pc-inspector-title h4 { margin: 0 0 8px 0; font-size: 22px; font-weight: 600; color: var(--pc-text); }
.pc-inspector-title p { font-size: 15px; }

.pc-inspector-body {
	flex: 1;
	padding: 40px;
	overflow-y: auto;
	background: var(--pc-bg);
}
.pc-field {
	margin-bottom: 32px;
	max-width: 600px;
	background: var(--pc-bg-hover);
	padding: 24px;
	border-radius: var(--pc-radius);
	border: 1px solid var(--pc-border);
}
.pc-field > label {
	display: block;
	font-weight: 600;
	margin-bottom: 8px;
	font-size: 15px;
	color: var(--pc-text);
}

.pc-inspector-footer {
	padding: 24px 40px;
	border-top: 1px solid var(--pc-border);
	background: var(--pc-bg);
	display: flex;
	gap: 16px;
}
.pc-inspector-footer--split {
	justify-content: space-between;
}

/* Bulk specific styles */
.pc-inspector-panel--bulk .pc-inspector-header {
	background: var(--pc-bg-hover);
}
.pc-bulk-notice {
	display: flex;
	gap: 16px;
	padding: 20px;
	background: rgba(255, 170, 0, 0.1);
	border: 1px solid rgba(255, 170, 0, 0.4);
	border-radius: var(--pc-radius);
	margin-bottom: 32px;
	color: var(--pc-text);
	font-size: 15px;
	line-height: 1.5;
	align-items: flex-start;
}
.pc-bulk-icon { font-size: 24px; line-height: 1; }

/* Empty state icon placeholder */
.pc-empty-icon {
	font-size: 48px;
	margin-bottom: 16px;
	opacity: 0.5;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
	.pc-deck-policy { height: auto; max-height: none; }
	.pc-split-pane { flex-direction: column; height: 800px; }
	.pc-split-left { width: 100%; border-right: none; border-bottom: 1px solid var(--pc-border); max-height: 400px; }
	.pc-split-right { min-height: 500px; }
	.pc-member-add-row { flex-direction: column; align-items: stretch; }
	.pc-inspector-header, .pc-inspector-body, .pc-inspector-footer { padding: 24px; }
}
</style>`);

fs.writeFileSync('src/components/ProjectDeck/DeckCardPolicyManager.vue', updated);
