<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\ProjectCreatorAIO\AppInfo\Application::APP_ID, OCA\ProjectCreatorAIO\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\ProjectCreatorAIO\AppInfo\Application::APP_ID, OCA\ProjectCreatorAIO\AppInfo\Application::APP_ID . '-main');

?>

<div id="projectcreatoraio"></div>

<!-- Required by the Nextcloud Viewer app (mount point for the overlay) -->
<div id="viewer"></div>
