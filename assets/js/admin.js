/**
 * Sportedia Admin JavaScript
 */

(function($) {
  'use strict';

  $(document).ready(function() {

    // Global Notice Dismissal
    $(document).on('click', '.sp-notice-dismiss', function() {
      $(this).closest('.sp-notice').fadeOut(200, function() {
        $(this).remove();
      });
    });

    // Delete Confirmation
    $(document).on('click', '.sp-confirm-delete', function(e) {
      if (!confirm(sportediaVars.confirmDelete || 'Are you sure you want to delete this item?')) {
        e.preventDefault();
        return false;
      }
    });

    // AJAX Player Search Autocomplete in Session Management
    let searchTimer = null;
    const $searchInput = $('#sp-player-search-input');
    const $resultsContainer = $('#sp-player-search-results');

    if ($searchInput.length) {
      $searchInput.on('keyup input', function() {
        const term = $.trim($(this).val());
        clearTimeout(searchTimer);

        if (term.length < 2) {
          $resultsContainer.hide().empty();
          return;
        }

        searchTimer = setTimeout(function() {
          $resultsContainer.html('<div class="sp-autocomplete-item"><span class="sp-autocomplete-item-main">Searching...</span></div>').show();

          $.ajax({
            url: sportediaVars.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
              action: 'sportedia_search_players',
              nonce: sportediaVars.nonce,
              term: term
            },
            success: function(response) {
              $resultsContainer.empty();
              if (response.success && response.data.length > 0) {
                $.each(response.data, function(idx, player) {
                  const itemHtml = `
                    <div class="sp-autocomplete-item sp-add-player-btn" data-id="${player.id}" data-name="${player.full_name}" data-code="${player.player_code}">
                      <div>
                        <div class="sp-autocomplete-item-main">${player.full_name} (${player.player_code})</div>
                        <div class="sp-autocomplete-item-sub">Age: ${player.age} | Gender: ${player.gender} | Sport: ${player.sport_name || 'N/A'} | Level: ${player.level || 'N/A'}</div>
                      </div>
                      <span class="sp-btn sp-btn-primary sp-btn-sm">Select</span>
                    </div>
                  `;
                  $resultsContainer.append(itemHtml);
                });
                $resultsContainer.show();
              } else {
                $resultsContainer.html('<div class="sp-autocomplete-item"><span class="sp-autocomplete-item-main">No active players found matching search.</span></div>').show();
              }
            },
            error: function() {
              $resultsContainer.html('<div class="sp-autocomplete-item"><span class="sp-autocomplete-item-main" style="color:red;">Error searching players.</span></div>').show();
            }
          });
        }, 250);
      });

      // Hide autocomplete when clicking outside
      $(document).on('click', function(e) {
        if (!$(e.target).closest('.sp-search-container').length) {
          $resultsContainer.hide();
        }
      });
    }

    // Modal Helpers
    window.spOpenModal = function(modalId) {
      $('#' + modalId).css('display', 'flex').hide().fadeIn(200);
    };

    window.spCloseModal = function(modalId) {
      $('#' + modalId).fadeOut(200);
    };

    $(document).on('click', '.sp-modal-close', function() {
      $(this).closest('.sp-modal-overlay').fadeOut(200);
    });

  });

})(jQuery);
