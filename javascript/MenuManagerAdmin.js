
(function($) {
    /**
     * A workaround for $.post not passing the XHR to the callback function.
     */
    var __last_xhr;

    $(function() {
        $('#left').fn({
            /**
             * Updates the creation forms in the left area when the selected workflow item has changed.
             *
             * @param {DOMObject} item
             */
            changeWorkflowItem: function() {
                var current = $('#Workflows').jstree('get_selected');

                if(!current.length) {
                    $('#ToggleForms').hide();
                    return;
                }

                switch(current.attr('data-type')) {
                    case 'Menu':
                        $('#ToggleForms, #Form_CreateMenuItemForm').show();
                        $('#Form_CreateMenuItemForm_ParentID').val('0');
                        $('#Form_CreateMenuItemForm_MenuID').val(current.attr('data-id'));
                        break;
                    case 'MenuItem':
                        $('#ToggleForms, #Form_CreateMenuItemForm').show();
                        $('#Form_CreateMenuItemForm_ParentID').val(current.attr('data-id'));
                        $('#Form_CreateMenuItemForm_MenuID').val(current.attr('data-menu-id'));
                        break;
                    default:
                        $('#ToggleForms, #Form_CreateMenuItemForm').hide();
                        break;
                }
            }
        });

        $('#ModelAdminPanel').fn({
            /**
             * Loads a new form from a URL, and displays the status message attached to the response. This overloads the
             * default ModelAdmin implementation to allow attaching data to the request.
             *
             * @param {String} url
             * @param {Object} data
             * @param {Function} callback
             */
            loadForm: function(url, data, callback) {
                statusMessage(ss.i18n._t('AdvancedWorkflow.LOADING', 'Loading...'));
                
                $(this).load(url, data, responseHandler(function(response, status, xhr) {
                    $('#form_actions_right').remove();
                    Behaviour.apply();
                    if(window.onresize) window.onresize();
                    if(callback) $(this).each(callback, [response, status, xhr]);
                }));
            }
        });

        $('#Workflows').jstree({
            plugins: [ 'themes', 'json_data', 'ui', 'crrm', 'dnd' ],
            json_data: {
                ajax: {
                    url:  function() {
                        return this.get_container().attr('href');
                    },
                    data: function(n) {
                        return {
                            id : n.attr ? n.attr('data-id') : 0, 
                            class: n.attr ? n.attr('data-class') : ''
                        };                    
                    }
                }
            },
            crrm: {
                move: {
                    check_move: function(move) {
                        var parent = this._get_parent(move.o);

                        if(!parent) return false;
                        if(parent == -1) parent = this.get_container();

                        return (parent === move.np || parent[0] && move.np[0] && parent[0] === move.np[0]);
                    }
                }
            },
            dnd : {
                drop_target : ".jstree-leaf",
                drag_target : ".jstres-leaf"
            }
        });

        $('#Workflows').bind('move_node.jstree', function(event, args) {
            var link = $('#Workflows').attr('data-href-sort');
            var tree = args.inst;
            var obj  = $(args.rslt.o);
            var data = 'type=' + obj.attr('data-type');

            if(obj.parent().parent().is('li')) {
                data += '&parent_id=' + obj.parent().parent().attr('data-id');
            }

            obj.parent().children('li').each(function() {
                data += '&ids[]=' + $(this).attr('data-id');
            });

            statusMessage(ss.i18n._t('AdvancedWorkflow.SAVINGORDER', 'Saving order...'));
            __last_xhr = $.post(link, data, responseHandler());
        });

        $('#Workflows').bind('select_node.jstree', function(event, args) {
            var self = $(args.args[0]);

            $('#ModelAdminPanel').fn('loadForm', self.attr('href'));
            $('#left').fn('changeWorkflowItem');

            return false;
        });

        $('#Workflows').bind('reopen.jstree', function(event, args) {
            $('#left').fn('changeWorkflowItem');
        });
    });

    $('#left form').live('submit', function() {
        var form = $(this);

        $('#ModelAdminPanel').fn('loadForm', form.attr('action'), form.formToArray(), function() {
            // if the record has been written to the database, reload the relevant portion of the tree
            if($('#Form_EditForm').length) {
                switch(form.attr('id')) {
                    case 'Form_EditMenuForm':
                        $('#Workflows').jstree('refresh');
                        break;
                    case 'Form_CreateMenuForm':
                        $('#Workflows').jstree('refresh');
                        break;
                    case 'Form_CreateMenuItemForm':
                        $('#Workflows').jstree('refresh',
                        '#WorkflowDefinition_' + $('input[name=ParentID]', form).val());
                        break;
                    default:
                        $('#Workflows').jstree('refresh');
                        break;
                }
            }

            $('select', form).val('');
            $('input[type=submit]', form).removeClass('loading');
        });

        return false;
    });

    /**
     * Overloads the default ModelAdmin add/save/delete handler in order to refresh the tree when changes are made.
     *
     * @param {Event} event
     */
    $('#form_actions_right input').live('click', function() {
        
        var button = $(this).addClass('loading');
        var form = $('#right form');
        var action = form.attr('action') + '?' + $(this).fieldSerialize();
        var isDelete = ($(this).attr('name') == 'action_doDelete');

        if(isDelete) {
            if(!confirm(ss.i18n._t('AdvancedWorkflow.REALLYDELETE', 'Do you really want to delete this?'))) {
                button.removeClass('loading');
                return false;
            }
        } else {
            if(typeof tinyMCE != 'undefined') tinyMCE.triggerSave();
        }

        $('#ModelAdminPanel').fn('loadForm', action, form.formToArray(), responseHandler(function() {
            button.removeClass('loading');

            
            if(!isDelete) {
                if($('#right #ModelAdminPanel form').hasClass('validationerror')) {
                    errorMessage(ss.i18n._t('AdvancedWorkflow.VALIDATIONERROR', 'Validation Error'));
                    return;
                } else {
                    statusMessage(ss.i18n._t('AdvancedWorkflow.SAVED', 'Saved'), 'good');
                }
            }

            var node = -1;

            $('#Workflows').jstree('refresh', node);
        }));

        return false;
    });

    /**
     * A simple wrapper around an AJAX request response handler function that also shows the status text attached to
     * the response as a status message.
     *
     * @param   {Function} callback
     * @returns Function
     */
    function responseHandler(callback) {
        return function(response, status, xhr) {
            if(!xhr && __last_xhr) xhr = __last_xhr;

            if(status == 'success') {
                statusMessage(xhr.statusText, 'good');
            } else {
                errorMessage(xhr.statusText);
            }

            if(callback) $(this).each(callback, [response, status, xhr]);
        }
    }
})(jQuery);