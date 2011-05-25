<?php

/**
 * An admin interface for managing workflow definitions, actions and transitions.
 *
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage admin
 */
class MenuManagerAdmin extends ModelAdmin {

    public static $title = 'Menus';
    public static $menu_title = 'Menus';
    public static $url_segment = 'menus';
    public static $managed_models = array(
        'Menu' => array('record_controller' => 'MenuManagerAdmin_RecordController'),
        'MenuItem' => array('record_controller' => 'MenuManagerAdmin_RecordController')
    );
    public static $allowed_actions = array(
        'tree',
        'sort',
        'CreateMenuForm',
        'CreateMenuItemForm'
    );

    /**
     * @return string
     */
    public function tree($request) {
        $data = array();
        $class = $request->getVar('class');
        $id = $request->getVar('id');

        if ($id == 0) {
            $items = singleton('MenuManagerService')->getMenus();
            $type = 'Menu';
        } elseif ($class == 'Menu') {
            $items = DataObject::get('MenuItem', '"MenuID" = ' . (int) $id . ' AND ParentID = 0');
            $type = 'MenuItem';
        } elseif ($class == 'MenuItem') {
            $items = DataObject::get('MenuItem', '"ParentID" = ' . (int) $id);
            $type = 'MenuItem';
        }

        if ($items)
            foreach ($items as $item) {

                if ($item->class == "MenuItem") {

                    $new = array(
                        'data' => array(
                            'title' => $item->Title,
                            'attr' => array('href' => $this->Link("$type/{$item->ID}/edit")),
                            'icon' => $item->stat('icon')),
                        'attr' => array(
                            'id' => "{$type}_{$item->ID}",
                            'title' => Convert::raw2att($item->Title),
                            'data-id' => $item->ID,
                            'data-type' => $type,
                            'data-class' => $item->class,
                            'data-menu-id' => $item->Menu()->ID)
                    );
                } else {
                    $new = array(
                        'data' => array(
                            'title' => $item->Title,
                            'attr' => array('href' => $this->Link("$type/{$item->ID}/edit")),
                            'icon' => $item->stat('icon')),
                        'attr' => array(
                            'id' => "{$type}_{$item->ID}",
                            'title' => Convert::raw2att($item->Title),
                            'data-id' => $item->ID,
                            'data-type' => $type,
                            'data-class' => $item->class)
                    );
                }


                if ($item->numChildren() > 0) {
                    $new['state'] = 'closed';
                }

                $data[] = $new;
            }

        return Convert::raw2json($data);
    }

    /**
     * @return string
     */
    public function sort($request) {
        $service = singleton('MenuManagerService');
        $type = $request->postVar('type');
        $order = $request->postVar('ids');
        $parentId = $request->postVar('parent_id');

        switch ($type) {
            case 'MenuItem':
                $current = $service->getDefinitions();
                break;
            case 'WorkflowAction':
                $current = DataObject::get('WorkflowAction', sprintf('"WorkflowDefID" = %d', $parentId));
                break;
            case 'WorkflowTransition':
                $current = DataObject::get('WorkflowTransition', sprintf('"ActionID" = %d', $parentId));
                break;
            default:
                return $this->httpError(400, _t('AdvancedWorkflowAdmin.INVALIDSORTTYPE', 'Invalid sort type.'));
        }

        if (!$order || count($order) != count($current)) {
            return new SS_HTTPResponse(
                    null, 400, _t('AdvancedWorkflowAdmin.INVALIDSORT', 'An invalid sort order was specified.')
            );
        }

        $service->reorder($current, $order);

        return new SS_HTTPResponse(
                null, 200, _t('AdvancedWorkflowAdmin.SORTORDERSAVED', 'The sort order has been saved.')
        );
    }

    /**
     * @return Form
     */
    public function CreateMenuForm() {
        return new Form(
                $this,
                'CreateMenuForm',
                new FieldSet(),
                new FieldSet(
                        new FormAction('doCreateMenu', "Create Menu"))
        );
    }

    /**
     * @return Form
     */
    public function CreateMenuItemForm() {
        return new Form(
                $this,
                'CreateMenuItemForm',
                new FieldSet(
                        new HiddenField('MenuID'),
                        new HiddenField('ParentID')),
                new FieldSet(
                        new FormAction('doCreateMenuItem', "Create Menu Item"))
        );
    }

    /**
     * Creates a workflow item - a definition, action, transition or any subclasses
     * of these.
     *
     * @param  array $data
     * @param  Form $form
     * @return string
     */
    public function doCreateMenu($data, $form) {
        // assume the form name is in the form CreateTypeForm
        $data = $form->getData();
        $type = substr($form->Name(), 6, -4);
        $allowSelf = ($type != 'WorkflowAction');

        // determine the class to create - if it is manually specified then use that,
        // falling back to creating an object of the root type if allowed.
        if (isset($data['Class']) && class_exists($data['Class'])) {
            $class = $data['Class'];
            $valid = is_subclass_of($class, $type) || ($allowSelf && $class == $type);

            if (!$valid)
                return new SS_HTTPResponse(
                        null, 400, _t('AdvancedWorkflowAdmin.INVALIDITEM', 'An invalid workflow item was specified.')
                );
        } else {
            $class = $type;

            if (!$allowSelf)
                return new SS_HTTPResponse(
                        null, 400, _t('AdvancedWorkflowAdmin.MUSTSPECIFYITEM', 'You must specify a workflow item to create.')
                );
        }

        // if an add form can be returned without writing a new rcord to the database,
        // then just do that
        if (array_key_exists($class, $this->getManagedModels())) {
            $form = $this->$type()->AddForm();
            $title = singleton($type)->singular_name();

            if ($type == 'WorkflowTransition') {
                $form->dataFieldByName('ActionID')->setValue($parentId);
            }
        } else {
            $record = new $class;
            $record->Title = sprintf(_t('AdvancedWorkflowAdmin.NEWITEM', 'New %s'), $record->singular_name());

            if ($type == 'WorkflowAction') {
                $record->WorkflowDefID = $parentId;
            } elseif ($type == 'WorkflowTransition') {
                $record->ActionID = $parentId;
            }

            $record->write();

            $control = $this->getRecordControllerClass('WorkflowDefinition');
            $control = new $control($this->$type(), null, $record->ID);
            $form = $control->EditForm();
            $title = $record->singular_name();
        }

        return new SS_HTTPResponse(
                $this->isAjax() ? $form->forAjaxTemplate() : $form->forTemplate(), 200,
                sprintf(_t('AdvancedWorkflowAdmin.CREATEITEM', 'Fill out this form to create a "%s".'), $title)
        );
    }

    /**
     * Creates a workflow item - a definition, action, transition or any subclasses
     * of these.
     *
     * @param  array $data
     * @param  Form $form
     * @return string
     */
    public function doCreateMenuItem($data, $form) {
        // assume the form name is in the form CreateTypeForm
        $data = $form->getData();
        $type = substr($form->Name(), 6, -4);
        $allowSelf = ($type != 'WorkflowAction');

        // determine the class to create - if it is manually specified then use that,
        // falling back to creating an object of the root type if allowed.
        if (isset($data['Class']) && class_exists($data['Class'])) {
            $class = $data['Class'];
            $valid = is_subclass_of($class, $type) || ($allowSelf && $class == $type);

            if (!$valid)
                return new SS_HTTPResponse(
                        null, 400, _t('AdvancedWorkflowAdmin.INVALIDITEM', 'An invalid workflow item was specified.')
                );
        } else {
            $class = $type;

            if (!$allowSelf)
                return new SS_HTTPResponse(
                        null, 400, _t('AdvancedWorkflowAdmin.MUSTSPECIFYITEM', 'You must specify a workflow item to create.')
                );
        }

        // check that workflow actions and transitions have valid parent id values.
        $parentId = $data['ParentID'];
        $menuId = $data['MenuID'];
        $parentClass = "MenuItem";

        if ($parentId != "0") {
            if (!is_numeric($parentId) || !DataObject::get_by_id($parentClass, $parentId)) {
                return new SS_HTTPResponse(
                        null, 400, _t('AdvancedWorkflowAdmin.INVALIDPARENT', 'An invalid parent was specified.')
                );
            }
        }

        // if an add form can be returned without writing a new rcord to the database,
        // then just do that
        if (array_key_exists($class, $this->getManagedModels())) {
            $form = $this->$type()->AddForm();
            $title = singleton($type)->singular_name();

            if ($type == 'MenuItem') {
                if($parentId != "0")
                    $form->dataFieldByName('ParentID')->setValue($parentId);
                $form->dataFieldByName('MenuID')->setValue($menuId);
            }
        } else {
            $record = new $class;
            $record->Title = sprintf(_t('AdvancedWorkflowAdmin.NEWITEM', 'New %s'), $record->singular_name());

            if ($type == 'WorkflowAction') {
                $record->WorkflowDefID = $parentId;
            } elseif ($type == 'WorkflowTransition') {
                $record->ActionID = $parentId;
            }

            $record->write();

            $control = $this->getRecordControllerClass('WorkflowDefinition');
            $control = new $control($this->$type(), null, $record->ID);
            $form = $control->EditForm();
            $title = $record->singular_name();
        }

        return new SS_HTTPResponse(
                $this->isAjax() ? $form->forAjaxTemplate() : $form->forTemplate(), 200,
                sprintf(_t('AdvancedWorkflowAdmin.CREATEITEM', 'Fill out this form to create a "%s".'), $title)
        );
    }

}

/**
 * A record controller that hides the "Back" button, and shows a message on deletion rather than redirecting to
 * the search form.
 *
 * @package    advancedworkflow
 * @subpackage admin
 */
class MenuManagerAdmin_RecordController extends ModelAdmin_RecordController {

    /**
     * @return Form
     */
    public function EditForm() {
        $form = parent::EditForm();
        $form->Actions()->removeByName('action_goBack');

        return $form;
    }

    /**
     * @return string
     */
    public function doDelete() {
        if ($this->currentRecord->canDelete()) {
            $this->currentRecord->delete();

            $form = new Form(
                            $this,
                            'EditForm',
                            new FieldSet(new LiteralField(
                                            'RecordDeleted',
                                            '<p>' . _t('AdvancedWorkflowAdmin.RECORDDELETED', 'This record has been deleted.') . '</p>'
                            )),
                            new FieldSet()
            );
            return $form->forTemplate();
        } else {
            return $this->redirectBack();
        }
    }

}