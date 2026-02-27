<template>
	<div class="pc-deck-policy">
		<div class="pc-deck-policy__header">
			<h3>Card Permissions</h3>
			<p class="muted">Configure who can move cards and who can approve/mark them done.</p>
		</div>

		<div v-if="loading" class="muted">Loading permissions…</div>
		<div v-else-if="error" class="muted error">{{ error }}</div>

		<div v-else>
			<div v-if="settings.permissionMode !== 'card_policy'" class="enable">
				<p class="muted">This board is using legacy permissions.</p>
				<NcButton type="primary" @click="enable">Enable card-based permissions</NcButton>
			</div>

			<div v-else>
				<div class="section">
					<div class="pc-rules-header">
						<div>
							<h4>Board Roles & Members</h4>
							<p class="muted">Define who belongs to which role on this board.</p>
						</div>
					</div>

					<div class="pc-role-legend">
						<span v-for="role in roles" :key="role.id" class="pc-chip" :style="chipStyleByKey(role.roleKey)">
							<span class="pc-dot" :style="{ background: role.color }" />
							{{ role.name }} <span class="muted" style="margin-left:4px">({{ role.roleKey }})</span>
						</span>
					</div>

					<div class="pc-toolbar pc-toolbar--add-member">
						<div class="pc-flex-1">
							<NcSelect v-model="newMembership.user" :options="memberOptions" placeholder="Select a member..." label="label" track-by="value" />
						</div>
						<div class="pc-flex-1">
							<NcSelect v-model="newMembership.role" :options="roleOptions" placeholder="Assign to role..." label="label" track-by="value" />
						</div>
						<NcButton type="primary" :disabled="!canAddMembership" @click="addMembership">Add Member</NcButton>
					</div>

					<div class="pc-grid-wrap" v-if="memberships.length > 0">
						<div class="pc-grid-table">
							<div class="pc-grid-thead pc-grid-thead--members">
								<div class="pc-th">Member</div>
								<div class="pc-th">Role</div>
								<div class="pc-th pc-th-actions">Actions</div>
							</div>
							<div class="pc-grid-tbody">
								<div v-for="m in memberships" :key="m.id" class="pc-grid-tr pc-grid-tr--members">
									<div class="pc-td pc-member-name">
										{{ getMemberDisplayName(m.participant) }}
									</div>
									<div class="pc-td">
										<span class="pc-chip" :style="chipStyleByRoleId(m.roleId)">
											<span class="pc-dot" :style="{ background: roleColorById(m.roleId) }" />
											{{ roleNameById[m.roleId] || m.roleId }}
										</span>
									</div>
									<div class="pc-td pc-td-actions">
										<NcButton @click="removeMembership(m)" type="tertiary" size="small">Remove</NcButton>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="section pc-defaults-section">
					<div class="pc-rules-header">
						<div>
							<h4>Board Defaults</h4>
							<p class="muted">These permissions apply to all cards that don't have custom rules.</p>
						</div>
						<NcButton type="secondary" @click="saveDefaults">Save Defaults</NcButton>
					</div>

					<div class="pc-defaults-grid">
						<div class="pc-default-card">
							<div class="pc-default-card__header">
								<h5>Who can move cards</h5>
								<p class="muted">Allows moving cards between standard columns.</p>
							</div>
							<NcSelect v-model="defaults.move" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" />
						</div>
						<div class="pc-default-card">
							<div class="pc-default-card__header">
								<h5>Who can approve / mark done</h5>
								<p class="muted">Allows moving cards into the Approved/Done column.</p>
							</div>
							<NcSelect v-model="defaults.approve" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" />
						</div>
					</div>
				</div>

				<div class="section">
					<div class="pc-rules-header">
						<div>
							<h4>Per-card Rules</h4>
							<p class="muted">Cards without explicit rules inherit the board defaults.</p>
						</div>
						<div class="pc-rules-stats">
							<strong>{{ customCardCount }}</strong> custom / <strong>{{ cards.length }}</strong> total
						</div>
					</div>

					<div class="pc-toolbar">
						<input v-model.trim="cardSearch" type="search" placeholder="Search cards by name…" class="pc-search-input">
						<select v-model.number="stackFilter" class="pc-stack-select">
							<option :value="0">All stacks</option>
							<option v-for="s in stacks" :key="s.id" :value="Number(s.id)">{{ s.title }}</option>
						</select>
						<label class="pc-toggle-label">
							<input v-model="showDefaultCards" type="checkbox">
							Show default cards
						</label>
					</div>

					<div class="pc-grid-wrap">
						<div class="pc-grid-table">
							<div class="pc-grid-thead">
								<div class="pc-th">Card</div>
								<div class="pc-th">Move</div>
								<div class="pc-th">Approve</div>
								<div class="pc-th pc-th-actions">Actions</div>
							</div>
							<div class="pc-grid-tbody">
								<div v-if="visibleCards.length === 0" class="pc-empty">
									No cards found matching your criteria.
								</div>
								<div v-for="card in visibleCards" :key="card.id" class="pc-grid-tr">
									<div class="pc-td pc-td-card">
										<div class="pc-card-title">
											{{ card.title }}
											<span v-if="card.hasExplicitPolicy" class="pc-badge pc-badge--custom">Custom</span>
										</div>
										<div class="pc-card-stack muted">in {{ getStackTitle(card.stackId) }}</div>
									</div>
									<div class="pc-td">
										<span v-if="card.effectivePolicy && (card.effectivePolicy.move || []).length" class="pc-chips">
											<span v-for="rk in (card.effectivePolicy.move || [])" :key="`${card.id}-m-${rk}`" class="pc-chip" :style="chipStyleByKey(rk)">
												<span class="pc-dot" :style="{ background: roleColorByKey(rk) }" />
												{{ roleNameByKey(rk) }}
											</span>
										</span>
										<span v-else class="muted">—</span>
									</div>
									<div class="pc-td">
										<span v-if="card.effectivePolicy && (card.effectivePolicy.approve || []).length" class="pc-chips">
											<span v-for="rk in (card.effectivePolicy.approve || [])" :key="`${card.id}-a-${rk}`" class="pc-chip" :style="chipStyleByKey(rk)">
												<span class="pc-dot" :style="{ background: roleColorByKey(rk) }" />
												{{ roleNameByKey(rk) }}
											</span>
										</span>
										<span v-else class="muted">—</span>
									</div>
									<div class="pc-td pc-td-actions">
										<NcButton @click="openEditor(card)" type="secondary" size="small">Edit</NcButton>
										<NcButton v-if="card.hasExplicitPolicy" @click="resetCard(card)" type="tertiary" size="small">Reset</NcButton>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<NcModal v-if="editingCard" @close="closeEditor" :title="`Permissions: ${editingCard.title}`">
			<div class="pc-modal-body">
				<p class="muted">
					Override the default board permissions for this specific card. If you clear these fields, the card will revert to inheriting the board defaults.
				</p>
				<div class="pc-modal-field">
					<label>Who can move</label>
					<NcSelect v-model="editingCardEdits.move" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" />
				</div>
				<div class="pc-modal-field">
					<label>Who can approve (Done)</label>
					<NcSelect v-model="editingCardEdits.approve" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" />
				</div>
				<div class="pc-modal-actions">
					<NcButton @click="closeEditor">Cancel</NcButton>
					<NcButton type="primary" @click="saveEditor">Save Custom Rules</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcModal from '@nextcloud/vue/components/NcModal'
import { showError, showSuccess } from '@nextcloud/dialogs'

import { DeckService } from '../../Services/deck.js'

const deckService = DeckService.getInstance()

export default {
	name: 'DeckCardPolicyManager',
	components: { NcButton, NcSelect, NcModal },
	props: {
		boardId: { type: [String, Number], required: true },
		members: { type: Array, default: () => [] },
	},
	data() {
		return {
			loading: false,
			error: '',
			settings: { permissionMode: 'legacy', approvedStackId: null },
			roles: [],
			memberships: [],
			defaults: { move: [], approve: [] },
			defaultRoleKeys: { move: [], approve: [] },
			cards: [],
			newMembership: { role: null, user: null },
			stacks: [],
			stackFilter: 0,
			showDefaultCards: false,
			cardSearch: '',
			editingCard: null,
			editingCardEdits: { move: [], approve: [] },
		}
	},
	computed: {
		boardIdNum() {
			return Number(this.boardId)
		},
		roleOptions() {
			return this.roles.map(r => ({ value: r.roleKey, label: r.name }))
		},
		roleNameById() {
			const map = {}
			for (const r of this.roles) map[r.id] = r.name
			return map
		},
		memberOptions() {
			return (this.members || []).map(m => ({
				value: m.id,
				label: m.displayName || m.id,
			}))
		},
		canAddMembership() {
			return !!(this.newMembership.role && this.newMembership.user)
		},
		roleByKey() {
			const map = {}
			for (const r of this.roles) map[r.roleKey] = r
			return map
		},
		visibleCards() {
			const q = (this.cardSearch || '').toLowerCase()
			const filtered = (this.cards || [])
				.filter(c => (this.showDefaultCards ? true : !!c.hasExplicitPolicy))
				.filter(c => (this.stackFilter && Number(c.stackId) !== Number(this.stackFilter)) ? false : true)
				.filter(c => (q ? String(c.title || '').toLowerCase().includes(q) : true))

			return filtered.sort((a, b) => {
				const sa = this.getStackTitle(a.stackId)
				const sb = this.getStackTitle(b.stackId)
				if (sa !== sb) return sa.localeCompare(sb)
				return String(a.title || '').localeCompare(String(b.title || ''))
			})
		},
		customCardCount() {
			return (this.cards || []).filter(c => !!c.hasExplicitPolicy).length
		},
	},
	watch: {
		boardId: {
			handler() { this.load() },
			immediate: true,
		},
	},
	methods: {
		unwrap(data) {
			return data?.ocs?.data || data
		},
		async load() {
			this.loading = true
			this.error = ''
			try {
				const [raw, stacks] = await Promise.all([
					deckService.getCardPolicy(this.boardIdNum),
					deckService.listStacks(this.boardIdNum),
				])
				const data = this.unwrap(raw)
				this.settings = data.settings || { permissionMode: 'legacy', approvedStackId: null }
				this.roles = data.roles || []
				this.memberships = data.memberships || []
				this.defaultRoleKeys = data.defaultRoleKeys || { move: [], approve: [] }
				this.defaults = {
					move: (this.defaultRoleKeys.move || []).map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v })),
					approve: (this.defaultRoleKeys.approve || []).map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v })),
				}
				this.stacks = (stacks || []).map(s => ({ id: Number(s.id), title: String(s.title || '') }))
				this.cards = data.cards || []
				if (this.stackFilter && !this.stacks.find(s => Number(s.id) === Number(this.stackFilter))) {
					this.stackFilter = 0
				}
			} catch (e) {
				this.error = e?.response?.data?.ocs?.data?.message || e?.response?.data?.message || e?.message || 'Error'
			} finally {
				this.loading = false
			}
		},
		getStackTitle(stackId) {
			const s = this.stacks.find(s => Number(s.id) === Number(stackId))
			return s ? s.title : 'Unknown stack'
		},
		getMemberDisplayName(participant) {
			const m = this.members.find(x => x.id === participant || x.uid === participant)
			return m ? (m.displayName || m.uid || m.id) : participant
		},
		roleColorById(roleId) {
			const r = this.roles.find(x => x.id === roleId)
			return r?.color || 'var(--color-border)'
		},
		chipStyleByRoleId(roleId) {
			return { borderColor: this.roleColorById(roleId) }
		},
		roleNameByKey(roleKey) {
			return this.roleByKey[roleKey]?.name || roleKey
		},
		roleColorByKey(roleKey) {
			return this.roleByKey[roleKey]?.color || 'var(--color-border)'
		},
		chipStyleByKey(roleKey) {
			return { borderColor: this.roleColorByKey(roleKey) }
		},
		openEditor(card) {
			this.editingCard = card
			// Prefill with explicitly assigned roles if custom, otherwise default board roles
			const prefillMove = card.hasExplicitPolicy ? (card.policy?.move || []) : (this.defaultRoleKeys.move || [])
			const prefillApprove = card.hasExplicitPolicy ? (card.policy?.approve || []) : (this.defaultRoleKeys.approve || [])
			this.editingCardEdits = {
				move: prefillMove.map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v })),
				approve: prefillApprove.map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v })),
			}
		},
		closeEditor() {
			this.editingCard = null
			this.editingCardEdits = { move: [], approve: [] }
		},
		async saveEditor() {
			if (!this.editingCard) return
			try {
				await deckService.setCardPolicy(this.boardIdNum, this.editingCard.id, {
					move: this.editingCardEdits.move.map(o => o.value),
					approve: this.editingCardEdits.approve.map(o => o.value),
				})
				showSuccess('Saved')
				this.closeEditor()
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || e?.message || 'Error')
			}
		},
		async resetCard(card) {
			try {
				await deckService.clearCardPolicy(this.boardIdNum, card.id)
				showSuccess('Reset')
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || e?.message || 'Error')
			}
		},
		async enable() {
			try {
				await deckService.enableCardPolicy(this.boardIdNum)
				showSuccess('Enabled')
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || e?.message || 'Error')
			}
		},
		async addMembership() {
			try {
				await deckService.addCardPolicyMembership(this.boardIdNum, {
					roleKey: this.newMembership.role.value,
					participant: this.newMembership.user.value,
					participantType: 0,
				})
				this.newMembership.role = null
				this.newMembership.user = null
				showSuccess('Added')
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || e?.message || 'Error')
			}
		},
		async removeMembership(m) {
			try {
				await deckService.deleteCardPolicyMembership(this.boardIdNum, m.id)
				showSuccess('Removed')
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || e?.message || 'Error')
			}
		},
		async saveDefaults() {
			try {
				await deckService.updateCardPolicyDefaults(this.boardIdNum, {
					move: this.defaults.move.map(o => o.value),
					approve: this.defaults.approve.map(o => o.value),
				})
				showSuccess('Saved')
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || e?.message || 'Error')
			}
		},
	},
}
</script>

<style scoped>
.pc-deck-policy {
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-background-hover);
	padding: 14px;
	display: grid;
	gap: 14px;
}
.pc-deck-policy__header h3 {
	margin: 0;
}
.muted {
	color: var(--color-text-maxcontrast);
}
.error {
	color: var(--color-error);
}
.section {
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
	padding: 16px;
	display: grid;
	gap: 12px;
	margin-bottom: 12px;
}

/* Redesigned Table/Grid Layout */
.pc-rules-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
}
.pc-rules-header h4 {
	margin-top: 0;
	margin-bottom: 4px;
}
.pc-rules-stats {
	background: var(--color-background-dark);
	padding: 4px 12px;
	border-radius: 999px;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.pc-toolbar {
	display: flex;
	gap: 12px;
	margin-bottom: 16px;
	align-items: center;
	flex-wrap: wrap;
}
.pc-search-input, .pc-stack-select {
	border: 1px solid var(--color-border);
	border-radius: 6px;
	padding: 8px 12px;
	background: var(--color-main-background);
	color: var(--color-main-text);
}
.pc-search-input {
	flex: 1;
	min-width: 200px;
}
.pc-toggle-label {
	display: flex;
	align-items: center;
	gap: 6px;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
}

.pc-grid-wrap {
	border: 1px solid var(--color-border);
	border-radius: 8px;
	overflow: hidden;
	background: var(--color-main-background);
}
.pc-grid-thead {
	display: grid;
	grid-template-columns: minmax(200px, 2fr) 1.5fr 1.5fr 140px;
	gap: 16px;
	padding: 12px 16px;
	background: var(--color-background-hover);
	border-bottom: 1px solid var(--color-border);
	font-weight: bold;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	text-transform: uppercase;
}
.pc-grid-tr {
	display: grid;
	grid-template-columns: minmax(200px, 2fr) 1.5fr 1.5fr 140px;
	gap: 16px;
	padding: 12px 16px;
	border-bottom: 1px solid var(--color-border);
	align-items: center;
}
.pc-grid-tr:last-child {
	border-bottom: none;
}
.pc-grid-tr:hover {
	background: var(--color-background-hover);
}
.pc-empty {
	padding: 32px;
	text-align: center;
	color: var(--color-text-maxcontrast);
}

.pc-card-title {
	font-weight: 600;
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 4px;
}
.pc-card-stack {
	font-size: 12px;
}

.pc-badge {
	font-size: 11px;
	padding: 2px 6px;
	border-radius: 4px;
	font-weight: bold;
	text-transform: uppercase;
}
.pc-badge--custom {
	background: var(--color-primary-element);
	color: var(--color-primary-text);
}

.pc-chips, .pc-role-legend {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}
.pc-role-legend {
	margin-bottom: 8px;
}
.pc-chip {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid var(--color-border);
	font-size: 12px;
	background: var(--color-main-background);
	white-space: nowrap;
}
.pc-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
}

.pc-td-actions {
	display: flex;
	gap: 8px;
	justify-content: flex-end;
}

/* Modal styles */
.pc-modal-body {
	padding: 20px;
	display: grid;
	gap: 16px;
	width: 500px;
	max-width: 100%;
}
.pc-modal-field label {
	display: block;
	font-size: 13px;
	font-weight: bold;
	margin-bottom: 6px;
}
.pc-modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 12px;
	margin-top: 16px;
}

/* New layout styles */
.pc-toolbar--add-member {
	background: var(--color-background-hover);
	padding: 12px;
	border-radius: 8px;
	border: 1px solid var(--color-border);
	margin-bottom: 12px;
}
.pc-flex-1 {
	flex: 1;
	min-width: 150px;
}
.pc-grid-thead--members, .pc-grid-tr--members {
	grid-template-columns: 2fr 1fr 100px;
}
.pc-member-name {
	font-weight: 600;
}
.pc-defaults-section {
	background: var(--color-main-background);
}
.pc-defaults-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
	margin-top: 16px;
}
.pc-default-card {
	border: 1px solid var(--color-border);
	border-radius: 8px;
	padding: 16px;
	background: var(--color-background-hover);
	display: flex;
	flex-direction: column;
	gap: 12px;
}
.pc-default-card__header h5 {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
}
.pc-default-card__header p {
	margin: 4px 0 0;
	font-size: 12px;
	line-height: 1.4;
}

@media (max-width: 900px) {
	.pc-grid-thead {
		display: none;
	}
	.pc-grid-tr {
		grid-template-columns: 1fr;
		gap: 8px;
	}
	.pc-td-actions {
		justify-content: flex-start;
		margin-top: 8px;
	}
	.pc-defaults-grid {
		grid-template-columns: 1fr;
	}
	.pc-toolbar--add-member {
		flex-direction: column;
		align-items: stretch;
	}
}
</style>
