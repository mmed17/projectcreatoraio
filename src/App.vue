<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent';
import ProjectsHome from './components/ProjectsHome.vue';

export default {
	name: 'App',
	components: {
		NcAppContent,
		ProjectsHome,
	},
	data() {
		return {};
	},
}
</script>


<template>
	<NcAppContent>
		<ProjectsHome />
	</NcAppContent>
</template>
