<?php

/**
 * A workflow action describes a the 'state' a workflow can be in, and
 * the action(s) that occur while in that state. An action can then have
 * subsequent transitions out of the current state. 
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class MenuItem extends DataObject {

    public static $db = array(
        'Title' => 'Varchar(255)',
        'Position' => 'Int'
    );
    public static $default_sort = 'Position';
    public static $has_one = array(
        'Menu' => 'Menu',
        'Parent' => 'MenuItem',
        'LinkTo' => 'Page'
    );
    public static $has_many = array(
        'Children' => 'MenuItem'
    );
    public static $icon = 'menumanager/images/menuitem.png';

    /**
     * Perform whatever needs to be done for this action. If this action can be considered executed, then
     * return true - if not (ie it needs some user input first), return false and 'execute' will be triggered
     * again at a later point in time after the user has provided more data, either directly or indirectly.
     * 
     * @param  WorkflowInstance $workflow
     * @return bool Returns true if this action has finished.
     */
    /* CMS RELATED FUNCTIONALITY... */

    /**
     * Gets fields for when this is part of an active workflow
     */
    public function numChildren() {
        return count($this->Children());
    }

    public function getCMSFields() {
        $pages = DataObject::get('Page');
        if ($pages) {
            $pages = $pages->toDropdownMap('ID', 'Title', '(Select one)', true);
        }

        $fields = new FieldSet(new TabSet('Root'));
        
        $fields->addFieldToTab('Root.Main', new HiddenField('MenuID'));
        $fields->addFieldToTab('Root.Main', new HiddenField('ParentID'));
        $fields->addFieldToTab('Root.Main', new TextField('Title', 'Title'));
        $fields->addFieldToTab('Root.Main', new DropdownField('LinkToID', 'Page', $pages));

        return $fields;
    }

    public function summaryFields() {
        return array(
            'Title' => 'Title');
    }

}