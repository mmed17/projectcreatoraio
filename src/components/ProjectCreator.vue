<template>
	<div id="projectcreatoraio" :class="containerClasses">
		<div class="project-creator-form">
			<h1 class="project-creator-title">Create a New Project</h1>
			<p class="project-creator-subtitle">
				Fill out the details below to set up your new project environment.
			</p>

			<NcNoteCard v-if="submissionStatus" :type="submissionStatus" class="status-card">
				<strong>{{ statusMessage }}</strong>
				<p v-if="statusDescription" class="status-description">{{ statusDescription }}</p>
			</NcNoteCard>

			<form>
				<div class="form-row">
					<NcTextField v-model="project.name"
						label="Project Name*"
						class="form-row-item"
						placeholder="e.g., Q4 Marketing Campaign"
						:show-label="true"
						input-label="Project Name" />

					<NcTextField v-model="project.number"
						label="Project Number*"
						placeholder="e.g., P-2025-001"
						:show-label="true"
						input-label="Project Number"
						class="form-row-item" />
				</div>

				<div class="form-row">
					<NcTextArea
						v-model="project.description"
						class="form-row-item"
						label="Project description"
						placeholder="Provide some details"
						:show-label="true"
						input-label="Project Description"
						rows="4" />
				</div>

				<div class="form-row">
					<NcTextField
						v-model="project.required_preparation_weeks"
						type="number"
						min="0"
						label="Required Preparation Time (weeks)"
						class="form-row-item"
						placeholder="e.g., 2"
						:show-label="true"
						input-label="Required Preparation Time (weeks)" />
				</div>

				<div class="form-row">
					<NcTextField
						v-model="project.client_name"
						label="Client name"
						class="form-row-item"
						placeholder="e.g., ACME Corp"
						:show-label="true"
						input-label="Client name" />

					<NcTextField
						v-model="project.client_role"
						label="Client role"
						class="form-row-item"
						placeholder="e.g., Project sponsor"
						:show-label="true"
						input-label="Client role" />
				</div>

				<div class="form-row">
					<NcTextField
						v-model="project.client_phone"
						label="Client phone"
						class="form-row-item"
						placeholder="e.g., +1 555 123 4567"
						:show-label="true"
						input-label="Client phone" />

					<NcTextField
						v-model="project.client_email"
						label="Client email"
						class="form-row-item"
						placeholder="e.g., client@example.com"
						:show-label="true"
						input-label="Client email" />
				</div>

				<div class="form-row">
					<NcTextField
						v-model="project.client_address"
						label="Client address"
						class="form-row-item"
						placeholder="e.g., 12 Market Street"
						:show-label="true"
						input-label="Client address" />
				</div>

				<div class="form-row">
					<NcTextField
						v-model="project.loc_street"
						label="Project location street"
						class="form-row-item"
						placeholder="e.g., 45 Industrial Ave"
						:show-label="true"
						input-label="Project location street" />

					<NcTextField
						v-model="project.loc_city"
						label="Project location city"
						class="form-row-item"
						placeholder="e.g., Toronto"
						:show-label="true"
						input-label="Project location city" />
				</div>

				<div class="form-row">
					<NcTextField
						v-model="project.loc_zip"
						label="Project location ZIP"
						class="form-row-item"
						placeholder="e.g., 10001"
						:show-label="true"
						input-label="Project location ZIP" />
				</div>

				<div class="form-row">
					<NcSelect v-model="selectedProjectType"
						class="form-row-item"
						placeholder="Select project type"
						input-label="Project Type*"
						:options="PROJECT_TYPES"
						:show-label="true"
						:multiple="false" />
				</div>
				<div v-if="isAdmin" class="form-row"> 
					<OrganizationsFetcher
                        class="form-row-item"
                        input-label="Organization*"
                        placeholder="Search for an organization..."
						:model-value="project.organizationId"
						@update:modelValue="project.organizationId = $event"
						@error="handleDependencyError" />
                </div>

				<NcButton
					:disabled="isCreatingProject || !canSubmit"
					type="primary"
					:wide="true"
					@click="createProject"
					class="submit-button">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ isCreatingProject ? 'Creating Project...' : 'Create Project' }}
				</NcButton>

				<NcButton
					v-if="embedded"
					type="secondary"
					:wide="true"
					class="cancel-button"
					@click="$emit('cancel')">
					Cancel
				</NcButton>
			</form>
		</div>
	</div>
</template>

<script>
import NcTextField from '@nextcloud/vue/components/NcTextField';
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard';
import NcTextArea from '@nextcloud/vue/components/NcTextArea';
import NcButton from '@nextcloud/vue/components/NcButton';
import NcSelect from '@nextcloud/vue/components/NcSelect';
import Plus from 'vue-material-design-icons/Plus.vue';

import { getCurrentUser } from '@nextcloud/auth';
import { PROJECT_TYPES } from '../macros/project-types';
import { ProjectsService } from '../Services/projects';
import { Project } from '../Models/project';

import OrganizationsFetcher from './OrganizationsFetcher.vue'

const projectsService = ProjectsService.getInstance();

export default {
	name: 'ProjectCreator',
	emits: ['cancel', 'created'],
	props: {
		embedded: {
			type: Boolean,
			default: false,
		},
	},
	components: {
		NcButton,
		NcTextField,
		NcSelect,
		NcNoteCard,
		OrganizationsFetcher,
		NcTextArea,
		Plus,
	},
	data() {
		return {
			project: new Project(),
			isCreatingProject: false,
			submissionStatus: '',
			statusMessage: '',
			statusDescription: '', // NEW: To hold the detailed error message
			PROJECT_TYPES,
		};
	},
	computed: {
		containerClasses() {
			return {
				'project-creator-container': true,
				'project-creator-container--embedded': this.embedded,
			}
		},
		selectedProjectType: {
			get() {
				return this.PROJECT_TYPES.find((type) => type.id === this.project.type) || null;
			},
			set(option) {
				this.project.type = option ? option.id : null;
			},
		},
		isAdmin() {
			return !!getCurrentUser()?.isAdmin;
		},
		canSubmit() {
			if (!this.project.name || !this.project.number || isNaN(this.project.type)) {
				return false;
			}

			if (this.isAdmin && !this.project.organizationId) {
				return false;
			}

			return true;
		},
	},
	methods: {
		handleDependencyError(error) {
			this.showProjectCreationErrorMessage(error)
		},
		async createProject() {
			this.isCreatingProject = true;
			this.submissionStatus = '';
			this.statusMessage = '';
			this.statusDescription = ''; // UPDATED: Reset description

			try {
				const result = await projectsService.create(this.project);
				this.showProjectCreationSuccessMessage();
				this.resetProjectForm();
				this.$emit('created', result);
				
				setTimeout(() => {
					this.resetProjectCreationMessage();
				}, 4000);

			} catch (error) {
				this.showProjectCreationErrorMessage(error);
				console.error('Error creating project:', error);
			} finally {
				this.isCreatingProject = false;
			}
		},
		resetProjectForm() {
			this.project = new Project();
		},
		showProjectCreationSuccessMessage() {
			this.submissionStatus = 'success';
			this.statusMessage = 'Project has been created successfully';
		},
		resetProjectCreationMessage() {
			this.submissionStatus = '';
			this.statusMessage = '';
			this.statusDescription = '';
		},
		/**
		 * @param error {Error}
		 */
		showProjectCreationErrorMessage(error) {
			this.submissionStatus = 'error';
			
			// UPDATED: Get the message from the correct location
			// The server's JSON payload is usually in 'error.response.data'
			let fullMessage = 'An unknown error occurred.';
			if (error.response && error.response.data && error.response.data.message) {
				fullMessage = error.response.data.message;
			} else if (error.message) {
				fullMessage = error.message;
			}

			// This regex will now check the correct 'fullMessage'
			// I've also made it match 'Exception: ' OR 'OCSException: '
			// And I've added [\s\S]*? to make sure it works even if the message has newlines
			const ocsMatch = fullMessage.match(/(?:Exception|OCSException): ([\s\S]*?) in \/var\/www\/nextcloud\//);

			if (ocsMatch && ocsMatch[1]) {
				// We found a specific message
				this.statusMessage = 'Project creation failed'; // Generic title
				this.statusDescription = ocsMatch[1].trim(); // The user-friendly description
			} else {
				// Fallback: Show the main part of the message, but cut off the stack trace if possible
				const stackTraceSplit = fullMessage.split('\nStack trace:');
				this.statusMessage = stackTraceSplit[0];
				this.statusDescription = ''; // No separate description
			}
		},
	}
}
</script>

<style scoped>
.project-creator-container {
	padding: 48px;
	display: flex;
	justify-content: center;
	width: 100%;
}

.project-creator-container--embedded {
	padding: 0;
}

.project-creator-form {
	max-width: 700px;
	width: 100%;
	display: flex;
	flex-direction: column;
	gap: 24px;
}

.project-creator-title {
	font-size: 2em;
	font-weight: bold;
	color: var(--color-main-text);
	margin-bottom: 0;
}

.project-creator-subtitle {
	font-size: 1.1em;
	color: var(--color-text-maxcontrast);
	margin-top: -16px;
	margin-bottom: 16px;
}

.form-row {
	display: flex;
	gap: 24px;
	align-items: space-between;
	justify-content: center;
	margin: 8px 0px;
}

.form-row-item {
	flex: 1;
}

.submit-button {
	margin-top: 16px;
	height: 44px; 
	font-size: 1.1em;
}

.cancel-button {
	margin-top: 8px;
}

.status-card {
	margin-bottom: -8px;
}

.status-description {
	margin-top: 8px;
	margin-bottom: 0;
	font-size: 0.9em;
	word-break: break-word;
}
</style>
