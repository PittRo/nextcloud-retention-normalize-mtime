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

			const saveSetting = function(key, value) {
				return $.ajax({
					url: OC.generateUrl('/apps/retention-normalize-mtime/settings'),
					method: 'POST',
					data: {
						key: key,
						value: value,
					},
				});
			};
			
			Promise.all([
				saveSetting('limit_to_group', limitToGroup),
				saveSetting('limit_to_prefix', limitToPrefix)
			]).then(function() {
				msg.show().text('Settings saved successfully').addClass('success');
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
