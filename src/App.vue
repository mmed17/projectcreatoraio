<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import ProjectsHome from './components/ProjectsHome.vue'
import WhiteboardPopout from './components/ProjectWhiteboard/WhiteboardPopout.vue'

export default {
	name: 'App',
	components: {
		NcAppContent,
		ProjectsHome,
		WhiteboardPopout,
	},
	data() {
		return {
			popoutMode: null,
			popoutProjectId: null,
		}
	},
	computed: {
		isWhiteboardPopout() {
			return this.popoutMode === 'whiteboard' && !!this.popoutProjectId
		},
	},
	created() {
		try {
			const params = new URLSearchParams(window.location.search || '')
			const popout = params.get('popout')
			if (popout === 'whiteboard') {
				this.popoutMode = 'whiteboard'
				this.popoutProjectId = params.get('projectId')
			}
		} catch (e) {
			// ignore
		}
	},
}
</script>

<template>
	<NcAppContent>
		<WhiteboardPopout v-if="isWhiteboardPopout" :project-id="popoutProjectId" />
		<ProjectsHome v-else />
	</NcAppContent>
</template>
