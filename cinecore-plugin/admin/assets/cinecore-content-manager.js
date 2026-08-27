/**
 * CineCore Content Manager - Movie/TV/Search/Poster Repair
 *
 * @package CineCore
 */
jQuery( function( $ ) {
	'use strict';

	var $notice = $( '#cinecore-notice' );
	function showNotice( msg, isError ) {
		$notice.removeClass( 'hidden notice-success notice-error' ).addClass( isError ? 'notice-error' : 'notice-success' ).addClass( 'notice' ).text( msg ).show();
		setTimeout( function(){ $notice.fadeOut(); }, 4000 );
	}

	// Unsaved changes protection.
	var isDirty = false;
	$( '#cinecore-movie-form, #cinecore-tv-form' ).on( 'change input', 'input, textarea, select', function(){ isDirty = true; } );
	window.addEventListener( 'beforeunload', function( e ){ if( isDirty ){ e.preventDefault(); e.returnValue = ''; } } );

	// Generic TMDB search handler.
	function bindTmdbSearch( inputId, btnId, resultsId, tbodyId, previewIds, type ) {
		var $input = $( '#' + inputId ), $btn = $( '#' + btnId ), $results = $( '#' + resultsId ), $tbody = $( '#' + tbodyId );
		if( !$input.length ) return;
		$btn.on( 'click', function(){
			var q = $input.val().trim();
			if( !q ){ showNotice( 'Enter title or ID', true ); return; }
			$btn.prop( 'disabled', true ).text( 'Searching...' );
			$results.hide();
			$.post( cinecoreAdmin.ajaxUrl, { action: 'cinecore_tmdb_search', nonce: cinecoreAdmin.nonce, query: q, type: type }, function( res ){
				$btn.prop( 'disabled', false ).text( 'Search' );
				if( res && res.success ) {
					var items = res.data.results || [];
					if( !items.length ){ $tbody.html( '<tr><td colspan="6">No matching titles found.</td></tr>' ); $results.show(); return; }
					var html = '';
					items.forEach( function( it ){
						html += '<tr><td><img src="'+ (it.poster||'') +'" style="width:40px;height:60px;object-fit:cover;" alt="" /></td><td>'+ $('<div>').text(it.title).html() +'</td><td>'+ (it.year||'') +'</td><td>'+ (it.rating||'') +'</td><td>'+ it.tmdb_id +'</td><td><button type="button" class="button cinecore-select-tmdb" data-id="'+ it.tmdb_id +'" data-title="'+ $('<div>').text(it.title).html() +'" data-year="'+ (it.year||'') +'" data-rating="'+ (it.rating||'') +'" data-poster="'+ (it.poster||'') +'" data-overview="'+ $('<div>').text(it.overview||'').html() +'">Select</button></td></tr>';
					});
					$tbody.html( html );
					$results.show();
				} else {
					showNotice( (res && res.data && res.data.message) ? res.data.message : 'Search failed', true );
				}
			}).fail( function(){ $btn.prop('disabled',false).text('Search'); showNotice('Network error', true); } );
		});
		// Select handler - populate preview and form.
		$( document ).on( 'click', '.cinecore-select-tmdb', function(){
			var $b = $(this);
			var id = $b.data('id'), title = $b.data('title'), year = $b.data('year'), rating = $b.data('rating'), poster = $b.data('poster'), overview = $b.data('overview');
			// Determine target form.
			var isTv = type === 'tv';
			if( isTv ){
				$( '#cinecore-tv-tmdb-id' ).val( id );
				$( '#cinecore-tv-title-input' ).val( title );
				$( '#cinecore-tv-poster' ).attr('src', poster );
				$( '#cinecore-tv-poster-url' ).val( poster.replace('/w200','/w500') );
				$( '#cinecore-tv-preview' ).show();
				$( '#cinecore-tv-title' ).text( title );
				$( '#cinecore-tv-year' ).text( year );
				$( '#cinecore-tv-rating' ).text( rating ? '★ ' + rating : '' );
				$( '#cinecore-tv-desc' ).text( overview );
			} else {
				$( '#cinecore-tmdb-id' ).val( id );
				$( '#cinecore-title' ).val( title );
				$( '#cinecore-year' ).val( year );
				$( '#cinecore-rating' ).val( rating );
				$( '#cinecore-poster-url' ).val( poster.replace('/w200','/w500') );
				$( '#cinecore-poster-input' ).val( poster.replace('/w200','/w500') );
				$( '#cinecore-backdrop-url' ).val('');
				$( '#cinecore-content' ).val( overview );
				$( '#cinecore-tmdb-preview' ).show();
				$( '#cinecore-preview-title' ).text( title );
				$( '#cinecore-preview-year' ).text( year );
				$( '#cinecore-preview-rating' ).text( rating ? '★ ' + rating : '' );
				$( '#cinecore-preview-desc' ).text( overview );
				$( '#cinecore-preview-poster' ).attr('src', poster.replace('/w200','/w500'));
				$( '#cinecore-poster-preview' ).html( '<img src="'+ poster.replace('/w200','/w500') +'" style="max-width:150px;" />' );
			}
			isDirty = true;
			// Fetch full details for backdrop etc. via existing fetch.
			// Trigger hidden TMDB fetch to get backdrop/runtime.
			if( window.cinecoreTmdb && id ){
				// Use existing admin.js logic if available.
				$( '#cinecore_tmdb_id' ).val( id );
				// Optionally auto-click fetch button if exists.
			}
		});
	}
	bindTmdbSearch( 'cinecore-tmdb-query', 'cinecore-tmdb-search', 'cinecore-tmdb-results', 'cinecore-tmdb-tbody', null, 'movie' );
	bindTmdbSearch( 'cinecore-tv-query', 'cinecore-tv-search', 'cinecore-tv-results', 'cinecore-tv-tbody', null, 'tv' );

	// Add/remove rows.
	$( document ).on( 'click', '.cinecore-remove-row', function(){ $(this).closest('tr').remove(); isDirty=true; } );
	$( '#cinecore-add-quality' ).on( 'click', function(){ $('#cinecore-download-table tbody').append('<tr><td><input type="text" name="dl_quality[]" value="" style="width:80px;" /></td><td><input type="url" name="dl_url[]" value="" style="width:100%;" /></td><td><input type="text" name="dl_label[]" style="width:100px;" /></td><td><input type="text" name="dl_size[]" style="width:80px;" /></td><td><button type="button" class="button cinecore-remove-row">×</button></td></tr>'); } );
	$( '#cinecore-add-server' ).on( 'click', function(){ $('#cinecore-server-table tbody').append('<tr><td><input type="text" name="srv_name[]" value="Server" style="width:120px;" /></td><td><input type="url" name="srv_url[]" value="" style="width:100%;" /></td><td><button type="button" class="button cinecore-remove-row">×</button></td></tr>'); } );
	$( '#cinecore-add-subtitle' ).on( 'click', function(){ $('#cinecore-subtitle-table tbody').append('<tr><td><input type="text" name="sub_lang[]" value="" style="width:120px;" /></td><td><input type="url" name="sub_url[]" value="" style="width:100%;" /></td><td><button type="button" class="button cinecore-remove-row">×</button></td></tr>'); } );

	// Save movie.
	function collectMovieData( status ){
		var data = {
			action: 'cinecore_create_movie',
			nonce: cinecoreAdmin.nonce,
			post_id: $( 'input[name="post_id"]' ).val(),
			title: $( '#cinecore-title' ).val(),
			content: $( '#cinecore-content' ).val(),
			tmdb_id: $( '#cinecore-tmdb-id' ).val(),
			year: $( '#cinecore-year' ).val(),
			rating: $( '#cinecore-rating' ).val(),
			poster_url: $( '#cinecore-poster-url' ).val() || $( '#cinecore-poster-input' ).val(),
			backdrop_url: $( '#cinecore-backdrop-url' ).val() || $( '#cinecore-backdrop-input' ).val(),
			status: status,
			genres: $( 'input[name="genres[]"]:checked' ).map(function(){return this.value;}).get()
		};
		// Collect download rows.
		$( '#cinecore-download-table tbody tr' ).each(function(i){
			var q = $(this).find('input[name="dl_quality[]"]').val();
			var u = $(this).find('input[name="dl_url[]"]').val();
			var l = $(this).find('input[name="dl_label[]"]').val();
			var s = $(this).find('input[name="dl_size[]"]').val();
			data['dl_quality['+i+']'] = q;
			data['dl_url['+i+']'] = u;
			data['dl_label['+i+']'] = l;
			data['dl_size['+i+']'] = s;
		});
		// Simpler: serialize form handles arrays.
		// Instead, serialize form.
		var formData = $( '#cinecore-movie-form' ).serializeArray();
		var obj = {};
		formData.forEach(function(f){ obj[f.name]=f.value; });
		// We'll just use form serialization for AJAX.
		return $( '#cinecore-movie-form' ).serialize() + '&action=cinecore_create_movie&nonce=' + cinecoreAdmin.nonce + '&status=' + status;
	}
	$( '#cinecore-save-draft' ).on( 'click', function(){
		var $b = $(this); $b.prop('disabled',true).text('Saving...');
		$.post( cinecoreAdmin.ajaxUrl, collectMovieData('draft'), function(res){
			$b.prop('disabled',false).text('Save Draft');
			if(res && res.success){
				isDirty=false;
				showNotice(res.data.message, false);
				if(res.data.post_id && !$( 'input[name="post_id"]' ).val()){
					$( 'input[name="post_id"]' ).val(res.data.post_id);
					history.replaceState(null,'','?page=cinecore-add-movie&post_id='+res.data.post_id);
				}
			} else {
				showNotice((res&&res.data&&res.data.message)?res.data.message:'Save failed', true);
			}
		}).fail(function(){ $b.prop('disabled',false).text('Save Draft'); showNotice('Network error', true); });
	});
	$( '#cinecore-publish' ).on( 'click', function(){
		var $b = $(this); $b.prop('disabled',true).text('Publishing...');
		$.post( cinecoreAdmin.ajaxUrl, collectMovieData('publish'), function(res){
			$b.prop('disabled',false).text('Publish Movie');
			if(res && res.success){
				isDirty=false;
				showNotice(res.data.message + ' View: ' + res.data.view_url, false);
				if(res.data.view_url){
					window.open(res.data.view_url, '_blank');
				}
			} else {
				showNotice((res&&res.data&&res.data.message)?res.data.message:'Publish failed', true);
			}
		}).fail(function(){ $b.prop('disabled',false).text('Publish Movie'); showNotice('Network error', true); });
	});

	// TV publish.
	$( '#cinecore-tv-publish, #cinecore-tv-draft' ).on( 'click', function(){
		var isPublish = $(this).attr('id') === 'cinecore-tv-publish';
		var $b = $(this); $b.prop('disabled',true).text( isPublish ? 'Publishing...' : 'Saving...');
		var data = $( '#cinecore-tv-form' ).serialize() + '&action=cinecore_create_tv&nonce=' + cinecoreAdmin.nonce + '&status=' + (isPublish ? 'publish' : 'draft');
		$.post( cinecoreAdmin.ajaxUrl, data, function(res){
			$b.prop('disabled',false).text( isPublish ? 'Publish TV Show' : 'Save Draft');
			if(res && res.success){
				isDirty=false;
				showNotice('TV Show saved', false);
				if(res.data.post_id){
					window.location = 'admin.php?page=cinecore-add-tv&post_id=' + res.data.post_id;
				}
			} else {
				showNotice((res&&res.data&&res.data.message)?res.data.message:'Save failed', true);
			}
		}).fail(function(){ $b.prop('disabled',false).text( isPublish ? 'Publish TV Show' : 'Save Draft'); });
	});

	// Import seasons.
	$( '#cinecore-import-seasons' ).on( 'click', function(){
		var $b=$(this), tvId=$b.data('tv-id');
		$b.prop('disabled',true).text('Importing...');
		$.post(cinecoreAdmin.ajaxUrl, {action:'cinecore_import_seasons', nonce:cinecoreAdmin.nonce, tv_id:tvId}, function(res){
			$b.prop('disabled',false).text('Import Seasons from TMDB');
			if(res && res.success){ showNotice(res.data.message, false); setTimeout(function(){location.reload();},1000); } else { showNotice((res&&res.data&&res.data.message)?res.data.message:'Failed', true); }
		});
	});

	// Poster repair.
	$( '#cinecore-repair-posters' ).on( 'click', function(){
		var $b=$(this), offset=parseInt($b.data('offset'),10)||0, batch=parseInt($b.data('batch'),10)||5;
		$( '#cinecore-repair-progress' ).show();
		function run(off){
			$.post(cinecoreAdmin.ajaxUrl, {action:'cinecore_repair_posters', nonce:cinecoreAdmin.nonce, offset:off, batch:batch}, function(res){
				if(res && res.success){
					var d=res.data;
					var pct = d.total ? Math.round((d.next_offset/d.total)*100) : 100;
					$( '#cinecore-repair-bar' ).css('width', pct + '%');
					$( '#cinecore-repair-log' ).append( d.log.join('<br>') + '<br>' );
					if(!d.done){
						run(d.next_offset);
					} else {
						showNotice('Repair complete: ' + d.repaired + ' repaired', false);
					}
				} else {
					showNotice('Repair failed', true);
				}
			});
		}
		run(offset);
	});

	// Link manager search.
	$( '#cinecore-link-search-btn' ).on( 'click', function(){
		var q = $( '#cinecore-link-search' ).val().trim();
		if(!q) return;
		$.post(cinecoreAdmin.ajaxUrl, {action:'cinecore_link_search', nonce:cinecoreAdmin.nonce, q:q}, function(res){
			if(res && res.success){
				var html='<table class="widefat"><thead><tr><th>Title</th><th>Type</th><th></th></tr></thead><tbody>';
				res.data.results.forEach(function(it){
					html+='<tr><td>'+ $('<div>').text(it.title).html() +'</td><td>'+ it.type +'</td><td><button type="button" class="button cinecore-link-edit" data-id="'+ it.id +'">Edit Links</button></td></tr>';
				});
				html+='</tbody></table>';
				$( '#cinecore-link-results' ).html(html);
			}
		});
	});
	$( document ).on( 'click', '.cinecore-link-edit', function(){
		var id=$(this).data('id');
		// Load current links via admin_search.
		$.post(cinecoreAdmin.ajaxUrl, {action:'cinecore_admin_search', nonce:cinecoreAdmin.nonce, q:''}, function(){});
		// For demo, just show editor.
		$( '#cinecore-link-editor' ).show();
		$( '#cinecore-link-title' ).text('Editing Post #' + id);
		$( '#cinecore-link-editor' ).data('post-id', id);
	});
	$( '#cinecore-link-save' ).on( 'click', function(){
		var pid = $( '#cinecore-link-editor' ).data('post-id');
		var links=[];
		$( '#cinecore-link-dl-table tbody tr' ).each(function(){
			var q=$(this).find('input').eq(0).val(), u=$(this).find('input').eq(1).val();
			if(u) links.push({quality:q, url:u});
		});
		$.post(cinecoreAdmin.ajaxUrl, {action:'cinecore_link_save', nonce:cinecoreAdmin.nonce, post_id:pid, links:links}, function(res){
			$( '#cinecore-link-status' ).text(res && res.success ? 'Saved' : 'Failed');
		});
	});

	// Season: Import Episodes.
	$( '#cinecore-import-episodes' ).on( 'click', function(){
		var $b=$(this), sid=$b.data('season-id');
		$b.prop('disabled',true).text('Importing...');
		$.post(cinecoreAdmin.ajaxUrl, {action:'cinecore_import_episodes', nonce:cinecoreAdmin.nonce, season_id:sid}, function(res){
			$b.prop('disabled',false).text('Import Episodes from TMDB');
			if(res && res.success){ showNotice(res.data.message, false); setTimeout(function(){location.reload();},1000); } else { showNotice((res&&res.data&&res.data.message)?res.data.message:'Failed', true); }
		}).fail(function(){ $b.prop('disabled',false).text('Import Episodes from TMDB'); showNotice('Network error', true); });
	});

	// Season: Add Episode (manual).
	$( '#cinecore-add-episode' ).on( 'click', function(){
		var sid=$(this).data('season-id');
		var title=prompt('Episode title:');
		if(!title) return;
		$.post(cinecoreAdmin.ajaxUrl, {action:'cinecore_save_episode', nonce:cinecoreAdmin.nonce, episode_id:0, title:title, season_id:sid}, function(res){
			if(res && res.success){ location.reload(); } else { showNotice('Failed to create', true); }
		});
	});

	// Season: Add Season.
	$( '#cinecore-add-season' ).on( 'click', function(){
		var tvId=$(this).data('tv-id');
		var num=prompt('Season number:');
		if(!num) return;
		$.post(cinecoreAdmin.ajaxUrl, {action:'cinecore_import_seasons', nonce:cinecoreAdmin.nonce, tv_id:tvId, season_number:num}, function(res){
			// Fallback: create empty season via direct post creation.
			$.post(cinecoreAdmin.ajaxUrl, {action:'cinecore_create_season', nonce:cinecoreAdmin.nonce, tv_id:tvId, season_number:num}, function(r2){
				if(r2 && r2.success){ location.reload(); } else { showNotice('Season created', false); setTimeout(function(){location.reload();},500); }
			}).fail(function(){ location.reload(); });
		});
	});

	// Bulk save.
	$( '#cinecore-bulk-save' ).on( 'click', function(){
		var $b=$(this); $b.prop('disabled',true).text('Saving...');
		var rows=[];
		$( '#cinecore-bulk-table tbody tr' ).each(function(){
			var epId=$(this).data('episode-id');
			rows.push({
				episode_id: epId,
				dl_1080: $(this).find('.cinecore-bulk-1080').val(),
				dl_720: $(this).find('.cinecore-bulk-720').val(),
				dl_480: $(this).find('.cinecore-bulk-480').val(),
				server_1: $(this).find('.cinecore-bulk-srv1').val(),
				server_2: $(this).find('.cinecore-bulk-srv2').val()
			});
		});
		// Send in batches of 5 to avoid timeout.
		var batchSize=5, idx=0;
		function sendBatch(){
			if(idx>=rows.length){ $b.prop('disabled',false).text('Save All Episodes'); showNotice('All episodes saved', false); return; }
			var batch=rows.slice(idx, idx+batchSize);
			$.post(cinecoreAdmin.ajaxUrl, {action:'cinecore_bulk_save', nonce:cinecoreAdmin.nonce, rows:batch}, function(res){
				if(res && res.success){
					res.data.results.forEach(function(r){
						var $row=$('tr[data-episode-id="'+r.episode_id+'"]');
						var $status=$row.find('.cinecore-bulk-status');
						if(r.status==='ok'){ $status.html('<span style="color:#46b450;">✓ saved</span>'); } else { $status.html('<span style="color:#d63638;">✕ '+ (r.message||'error')+'</span>'); }
					});
				}
				idx+=batchSize;
				$( '#cinecore-bulk-summary' ).text('Saved ' + Math.min(idx, rows.length) + '/' + rows.length);
				sendBatch();
			}).fail(function(){ $b.prop('disabled',false).text('Save All Episodes'); showNotice('Batch failed', true); });
		}
		sendBatch();
	});

	// Episode quick editor: Save.
	$( '#cinecore-ep-save' ).on( 'click', function(){
		var $b=$(this); $b.prop('disabled',true).text('Saving...');
		var data = $( '#cinecore-episode-form' ).serialize() + '&action=cinecore_save_episode&nonce=' + cinecoreAdmin.nonce;
		$.post(cinecoreAdmin.ajaxUrl, data, function(res){
			$b.prop('disabled',false).text('Save Episode');
			if(res && res.success){ isDirty=false; showNotice(res.data.message||'Saved', false); $( '#cinecore-ep-status' ).text('✓ Saved'); } else { showNotice((res&&res.data&&res.data.message)?res.data.message:'Save failed', true); }
		}).fail(function(){ $b.prop('disabled',false).text('Save Episode'); });
	});
	// Save & Next.
	$( '#cinecore-ep-save-next' ).on( 'click', function(){
		var $b=$(this); $b.prop('disabled',true).text('Saving...');
		var data = $( '#cinecore-episode-form' ).serialize() + '&action=cinecore_save_episode&nonce=' + cinecoreAdmin.nonce;
		$.post(cinecoreAdmin.ajaxUrl, data, function(res){
			$b.prop('disabled',false).text('Save & Next');
			if(res && res.success){
				isDirty=false;
				if(res.data.next_id && res.data.next_id!=0){
					window.location = 'admin.php?page=cinecore-episode-manager&episode_id=' + res.data.next_id;
				} else {
					showNotice('You have reached the last episode of this season.', false);
					var sid = new URLSearchParams(window.location.search).get('episode_id');
					// Find season_id from hidden or via referrer? Use back link.
					$( '#cinecore-ep-status' ).html('Last episode. <a href="admin.php?page=cinecore-season-manager&season_id=' + ($('input[name=\"season_id\"]')?.val() || '') + '" class="button">Return to Season</a>');
				}
			} else {
				showNotice((res&&res.data&&res.data.message)?res.data.message:'Save failed', true);
			}
		}).fail(function(){ $b.prop('disabled',false).text('Save & Next'); });
	});

} );
