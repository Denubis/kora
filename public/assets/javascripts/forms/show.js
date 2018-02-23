var Kora = Kora || {};
Kora.Forms = Kora.Forms || {};

Kora.Forms.Show = function() {
  function clearSearch() {
    $('.search-js .icon-cancel-js').click();
  }

  function initializeSearch() {
    var $searchInput = $('.search-js input');

    $('.search-js i, .search-js input').click(function(e) {
      e.preventDefault();

      $(this).parent().addClass('active');
      $('.search-js input').focus();
    });

    $searchInput.focusout(function() {
      if (this.value.length == 0) {
        $(this).parent().removeClass('active');
        $(this).next().removeClass('active');
      }
    });

    $searchInput.keyup(function(e) {
      if (e.keyCode === 27) {
        $(this).val('');
      }

      if (this.value.length > 0) {
        $(this).next().addClass('active');
      } else {
        $(this).next().removeClass('active');
      }
    });

    $('.search-js .icon-cancel-js').click(function() {
      $searchInput.val('').blur().parent().removeClass('active');
    });
  }

  function initializeFieldSort() {
    // Initialize Custom Sort
    $('.field-toggle-js').click(function(e) {
      e.preventDefault();

      var $this = $(this);
      var $header = $this.parent().parent();
      var $field = $header.parent();
      var $content = $header.next();

      $this.children('.icon').toggleClass('active');
      $field.toggleClass('active');
      if ($field.hasClass('active')) {
        $header.addClass('active');
        $field.animate({
          height: $field.height() + $content.outerHeight(true) + 'px'
        }, 230);
        $content.effect('slide', {
          direction: 'up',
          mode: 'show',
          duration: 240
        });
      } else {
        $field.animate({
          height: '58px'
        }, 230, function() {
          $header.hasClass('active') ? $header.removeClass('active') : null;
          $content.hasClass('active') ? $content.removeClass('active') : null;
        });
        $content.effect('slide', {
          direction: 'up',
          mode: 'hide',
          duration: 240
        });
      }

    });

    $('.field-sort-js').sortable({
      helper: 'clone',
      revert: true,
      containment: '.form-show',
      connectWith: $('.field-sort-js'),
      items: '.field-container',
      update: function(event, ui) {
        pidsArray = $('.field-sort-js').sortable('toArray');

        // $.ajax({
        //   url: saveCustomOrderUrl,
        //   type: 'POST',
        //   data: {
        //     "_token": CSRFToken,
        //     "pids": pidsArray,
        //
        //   },
        //   success: function(result) {}
        // });
      }
    });

    $('.move-action-js').click(function(e) {
      e.preventDefault();

      var $this = $(this);
      var $headerInnerWrapper = $this.parent().parent();
      var $header = $headerInnerWrapper.parent();
      var $field = $header.parent();
      var $fieldContainer = $field.parent();
      // $field.prev().before(current);
      if ($this.hasClass('up-js')) {
        var $previousField = $fieldContainer.prev();
        if ($previousField.length == 0 || !$previousField.hasClass('field-container')) {
          // move to previous page if exists
          return;
        }

        $previousField.css('z-index', 999)
          .css('position', 'relative')
          .animate({
            top: $field.height()
          }, 300);
        $fieldContainer.css('z-index', 1000)
          .css('position', 'relative')
          .animate({
            top: '-' + $previousField.height()
          }, 300, function() {
            $previousField.css('z-index', '')
              .css('top', '')
              .css('position', '');
            $fieldContainer.css('z-index', '')
              .css('top', '')
              .css('position', '')
              .insertBefore($previousField);
          });
      } else {
        var $nextField = $fieldContainer.next();
        if ($nextField.length == 0 || !$nextField.hasClass('field-container')) {
          // move to next page if exists
          return;
        }

        $nextField.css('z-index', 999)
          .css('position', 'relative')
          .animate({
            top: '-' + $field.height()
          }, 300);
        $fieldContainer.css('z-index', 1000)
          .css('position', 'relative')
          .animate({
            top: $nextField.height()
          }, 300, function() {
            $nextField.css('z-index', '')
              .css('top', '')
              .css('position', '');
            $fieldContainer.css('z-index', '')
              .css('top', '')
              .css('position', '')
              .insertAfter($nextField);
          });
      }
    });
  }

  function initializePage() {
    Kora.Modal.initialize(); // Initialize modals

    pageTitles = document.getElementsByClassName('page-title-js');
    for (var i = 0; i < pageTitles.length; i++) {
      $pageTitle = $(pageTitles[i]);
      $pageTitle.attr('size',
        $pageTitle.attr('placeholder').length);
      
      $pageTitle.on('keyup blur', function(e) {
        $this = $(this);
        if ((e.key === "Enter" || e.type === "blur") && $this.val() !== '') {
          $.ajax({
            url: modifyFormPageRoute,
            type: 'POST',
            data: {
              '_token': CSRFToken,
              'method': renameMethod,
              'pageID': $this.attr('pageid'),
              'updatedName': $this.val()
            }
          })
          $this.attr('placeholder', $this.val());
          $this.attr('size', $this.val().length)
          $this.val('');
        }

        if (e.key === "Enter") {
          $this.blur()
        }
      });

      $pageTitle.on('click focus', function(e) {
        $(this).val($(this).attr('placeholder'));
      });
    }

    $('.delete-page-js').on('click', function(e) {
      e.preventDefault();
      var page = $(e.target).parent().data('page');

      var $deleteModal = $('.page-delete-modal-js');
      $deleteModal.find('.delete-page-confirm-js').data('page', page);

      Kora.Modal.open($deleteModal);
    });

    $('.delete-field-js').on('click', function(e) {
      e.preventDefault();
      var field = $(this).parents('.field').attr('id');
      var url = $(this).parents('.field').attr('delete-url');

      var $deleteModal = $('.field-delete-modal-js');
      $deleteModal.find('.delete-field-confirm-js').attr('field', field).attr('delete-url', url);

      Kora.Modal.open($deleteModal);
    })

    $('.move-action-page-js').on('click', function(e) {
      e.preventDefault();

      var $this = $(this);
      var $pageID = $this.attr('page_id');
      var $page = $this.parent().parent().parent();
      var $pageAdd = $page.next();
      
      if ($this.hasClass('up-js')) {
        var $previousPageAdd = $page.prev();
        var $previousPage = $previousPageAdd.prev();
        if ($previousPageAdd.length == 0) {
          // move to previous page if exists
          return;
        }

        $previousPage.css('z-index', 999)
          .css('position', 'relative')
          .animate({
            top: $page.height() + $previousPageAdd.height() + 120
          }, 300);
        $previousPageAdd.css('z-index', 999)
          .css('position', 'relative')
          .animate({
            top: $page.height() + $previousPageAdd.height() + 120
          }, 300);
        $pageAdd.css('z-index', 1000)
          .css('position', 'relative')
          .animate({
            top: '-' + ($previousPage.height() + $previousPageAdd.height() + 120)
          }, 300);
        $page.css('z-index', 1000)
          .css('position', 'relative')
          .animate({
            top: '-' + ($previousPage.height() + $previousPageAdd.height() + 120)
          }, 300, function() {
            $previousPage.css('z-index', '')
              .css('top', '')
              .css('position', '');
            $page.css('z-index', '')
              .css('top', '')
              .css('position', '')
              .insertBefore($previousPage);
            $previousPageAdd.css('z-index', '')
              .css('top', '')
              .css('position', '')
              .insertAfter($previousPage);
            $pageAdd.css('z-index', '')
              .css('top', '')
              .css('position', '')
              .insertAfter($page);
          });

        $.ajax({
          url: modifyFormPageRoute,
          type: 'POST',
          data: {
            '_token': CSRFToken,
            'method': upMethod,
            'pageID': $pageID
          }
        });
      } else {
        var $nextPage = $pageAdd.next();
        var $nextPageAdd = $nextPage.next();
        if ($nextPage.length == 0) {
          return;
        }

        $nextPage.css('z-index', 999)
          .css('position', 'relative')
          .animate({
            top: '-' + ($page.height() + $pageAdd.height() + 120)
          }, 300);
        $nextPageAdd.css('z-index', 999)
          .css('position', 'relative')
          .animate({
            top: '-' + ($page.height() + $pageAdd.height() + 120)
          }, 300);

        $pageAdd.css('z-index', 1000)
          .css('position', 'relative')
          .animate({
            top: $nextPage.height() + $pageAdd.height() + 120
          }, 300);
        $page.css('z-index', 1000)
          .css('position', 'relative')
          .animate({
            top: $nextPage.height() + $pageAdd.height() + 120
          }, 300, function() {
            $nextPage.css('z-index', '')
              .css('top', '')
              .css('position', '');
            $page.css('z-index', '')
              .css('top', '')
              .css('position', '')
              .insertAfter($nextPage);
            $pageAdd.css('z-index', '')
              .css('top', '')
              .css('position', '')
              .insertAfter($page);
            $nextPageAdd.css('z-index', '')
              .css('top', '')
              .css('position', '')
              .insertAfter($nextPage);
          });

        $.ajax({
          url: modifyFormPageRoute,
          type: 'POST',
          data: {
            '_token': CSRFToken,
            'method': downMethod,
            'pageID': $pageID
          }
        });
      }
    });
  }

  function initializePages() {
    initializePage();

    $('.pages-js').on('click', '.new-page-js', function(e) {
      e.preventDefault();
      var pageID = $(this).data('prev-page');
      var newPageNumber = $(this).data('new-page');
      var title = 'Page #' + newPageNumber;

      $.ajax({
        //We manually create the link in a cheap way because our JS isn't aware of the fid until runtime
        //We pass in a blank project to the action array and then manually add the id
        url: modifyFormPageRoute,
        type: 'POST',
        data: {
          '_token': CSRFToken,
          'method': addMethod,
          'aboveID': pageID,
          'newPageName': title
        },
        success: function(result) {
          location.reload();
        }
      });
    });

    $('.page-delete-modal-js').on('click', '.delete-page-confirm-js', function(e) {
      e.preventDefault();
      var pageID = $(this).data('page');

      $.ajax({
        //We manually create the link in a cheap way because our JS isn't aware of the fid until runtime
        //We pass in a blank project to the action array and then manually add the id
        url: modifyFormPageRoute,
        type: 'POST',
        data: {
          '_token': CSRFToken,
          'method': delMethod,
          'pageID': pageID
        },
        success: function(result) {
          location.reload();
        }
      });
    });

    $('.field-delete-modal-js').on('click', '.delete-field-confirm-js', function(e) {
      e.preventDefault();
      var fieldID = $(this).attr('field');
      var url = $(this).attr('delete-url');
      
      $.ajax({
        url: url,
        type: 'DELETE',
        data: {
          '_token': CSRFToken,
        },
        success: function(result) {
          location.reload();
        }
      })
    });
  }

  function initializeFieldToggles() {
    $('.expand-fields-js').on('click', function(e) {
      e.preventDefault();
      $('.card:not(.active) .field-toggle-js').click();
    });

    $('.collapse-fields-js').on('click', function(e) {
      e.preventDefault();
      $('.card.active .field-toggle-js').click();
    });
  }

  function initializeCheckboxes() {
    $('.preset-input-js').on('change', function(e) {
      var url = $(this).parents('.allowed-actions').attr('update-flag-url');

      $.ajax({
        url: url,
        type: 'PATCH',
        data: {
          '_token': CSRFToken,
          'flag': this.name,
          'value': this.checked ? 1 : 0
        }
      });
    });
  }

  initializeSearch();
  initializePages();
  initializeFieldSort();
  initializeFieldToggles();
  initializeCheckboxes();
}