(function() {
	'use strict';

	$(document).ready(function() {
		$('#retention-save-settings').on('click', function() {
			const button = $(this);
			const msg = $('#retention-settings-msg');
			
			button.prop('disabled', true);
			msg.text('Saving...').removeClass('success error');
			
			const limitToGroup = $('#limit-to-group').val();
			const limitToPrefix = $('#limit-to-prefix').val();
			
			Promise.all([
				OC.AppConfig.setValue('retention_normalize_mtime', 'limit_to_group', limitToGroup),
				OC.AppConfig.setValue('retention_normalize_mtime', 'limit_to_prefix', limitToPrefix)
			]).then(function() {
				msg.text('Settings saved successfully').addClass('success');
				button.prop('disabled', false);
				setTimeout(function() {
					msg.fadeOut();
				}, 3000);
			}).catch(function(error) {
				msg.text('Error saving settings: ' + error).addClass('error');
				button.prop('disabled', false);
			});
		});
	});
})();

