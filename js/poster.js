( function( $ ) {
	
	const theForm = document.getElementById('rest-form-new-post');

	if ( theForm ) {
		const formSubmit     = document.getElementById('submit');
		const postTitleField = document.querySelector('.form-title-input');
		const postTitleLabel = document.querySelector('.post-title-label');
		const postTextarea   = document.querySelector('.form-content-textarea');
		const postAuthor     = document.getElementsByName('post_author'); 
		
		postTitleField.addEventListener('focus', controlSubmit);

		function controlSubmit() {
			formSubmit.setAttribute('disabled', 'disabled');
			formSubmit.setAttribute('style','background-color:gray');
			console.log(postAuthor[0].value);
		}
		
		postTitleField.addEventListener('focusout', getEndPoint);

		formSubmit.addEventListener('click', postEndPoint);

		function postEndPoint(e) {
			e.preventDefault();
			console.log(formSubmit);
			if (postTitleField.value) {

				$.ajax( {
					url: wpApiSettings.root + 'myplugin/v1/form-submissions/' + postTitleField.value,
					method: 'POST',
					dataType:'json',
					data: {
						postContent: postTextarea.value
					},
					beforeSend: function(xhr) {
						xhr.setRequestHeader( 'X-WP-Nonce', wpApiSettings.nonce );
					}
				}).
				done( function( data ) {
					postTitleLabel.textContent = wma.added_post;
					postTitleField.value = '' ;
					postTextarea.value = '';
				})
				.fail( function( data ) {
					
					if ( data.status == '403') {
						postTitleLabel.setAttribute("style","color:red;font-weight:bolder");	
						postTitleLabel.textContent = wma.not_allowed
					} else {
						postTitleLabel.setAttribute("style","color:red;font-weight:bolder");
						postTitleLabel.textContent = data.responseJSON;
					}
					

				})
			}
		}
	
		function getEndPoint() {
			if ( postTitleField.value ) {

				$.ajax( {
					url: wpApiSettings.root + 'myplugin/v1/form-submissions/' + postTitleField.value,
					method: 'GET',
					dataType:'json',					
					beforeSend: function(xhr) {
						postTitleLabel.textContent = wma.checking;
					}
				}).
				
				done( function( data ) {
					postTitleLabel.setAttribute("style","color:green");
					postTitleLabel.textContent = wma.new_title;
					formSubmit.removeAttribute('style');
					formSubmit.removeAttribute('disabled');
				})
				.fail(function( data ) {
					postTitleLabel.setAttribute("style","color:red;font-weight:bolder");
					if ( data.status == '404') {
						postTitleLabel.textContent = wma.not_found
					} else {
						postTitleLabel.textContent = wma.title_exists
					}
					
				})
			}
		}
	}	
} )( jQuery );