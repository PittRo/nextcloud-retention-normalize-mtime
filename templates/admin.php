<?php
script('nextcloud-retention-normalize-mtime', 'admin');
style('nextcloud-retention-normalize-mtime', 'admin');
?>

<div id="retention-normalize-mtime-settings" class="section">
	<h2><?php p($l->t('Retention Normalize Mtime')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Configure which files should have their modification time normalized on upload.')); ?>
	</p>

	<div class="retention-settings-group">
		<h3><?php p($l->t('Filter Settings')); ?></h3>

		<p>
			<label for="limit-to-group"><?php p($l->t('Limit to Group')); ?></label>
			<input type="text"
				   id="limit-to-group"
				   name="limit_to_group"
				   value="<?php p($_['limit_to_group']); ?>"
				   placeholder="<?php p($l->t('e.g., hundh (leave empty for all users)')); ?>" />
			<em><?php p($l->t('Only normalize files from users in this group. Leave empty to process all users.')); ?></em>
		</p>

		<p>
			<label for="limit-to-prefix"><?php p($l->t('Limit to Folder Prefix')); ?></label>
			<input type="text"
				   id="limit-to-prefix"
				   name="limit_to_prefix"
				   value="<?php p($_['limit_to_prefix']); ?>"
				   placeholder="<?php p($l->t('e.g., /Retention (leave empty for all folders)')); ?>" />
			<em><?php p($l->t('Only normalize files in folders starting with this path. Leave empty to process all folders.')); ?></em>
		</p>

		<button id="retention-save-settings" class="button primary"><?php p($l->t('Save')); ?></button>
		<span id="retention-settings-msg" class="msg"></span>
	</div>
</div>
