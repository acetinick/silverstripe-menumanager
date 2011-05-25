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
class Menu extends DataObject {

    public static $db = array(
        'Title' => 'Varchar(255)',
        'Reference' => 'Varchar(255)',
        'Position' => 'Int',
        'HtmlID' => 'Varchar',
        'CssClass' => 'Varchar',
        'LastItemCssClass' => 'Varchar'
    );
    public static $default_sort = 'Position';
    public static $has_many = array(
        'MenuItems' => 'MenuItem'
    );
    public static $icon = 'menumanager/images/menu.png';

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
        return count($this->MenuItems());
    }

    public function getCMSFields() {
        $fields = new FieldSet(new TabSet('Root'));
        $fields->addFieldToTab('Root.Main', new TextField('Title', 'Title'));
        $fields->addFieldToTab('Root.Main', new TextField('Reference', 'Reference'));
        $fields->addFieldToTab('Root.Main', new TextField('HtmlID', 'HTML ID'));
        $fields->addFieldToTab('Root.Main', new TextField('CssClass', 'CSS Class'));
        $fields->addFieldToTab('Root.Main', new TextField('LastItemCssClass', 'Last Item CSS Class'));

        return $fields;
    }

    public function summaryFields() {
        return array(
            'Title' => 'Title',
            'Reference' => 'Reference');
    }

    public function Render() {
        $menu_items = $this->MenuItems("ParentID = 0");

        $html = "";

        if ($menu_items) {
            $html .= "<ul" . (($this->HtmlID) ? " id='" . $this->HtmlID . "'" : "") . (($this->CssClass) ? " class='" . $this->CssClass . "'" : "") . ">";

            foreach ($menu_items as $menu_item) {

                if ($menu_item->LinkTo()) {
                    $html .= "<li><a href='" . $menu_item->LinkTo()->URLSegment . "'>" . $menu_item->Title . "</a></li>";
                } else {
                    $html .= "<li><a href='#'>" . $menu_item->Title . "</a></li>";
                }

                if ($menu_item->Children()->Count() != 0) {
                    $html .= $this->RenderMenuItem($menu_item);
                }
            }

            $html .= "</ul>";
        }

        return $html;
    }

    public function RenderMenuItem($menu_item) {
        $menu_items = $menu_item->Children();

        $html = "";

        if ($menu_items) {
            $html .= "<ul>";

            foreach ($menu_items as $child) {

                if ($child->LinkTo()) {
                    $html .= "<li><a href='" . $child->LinkTo()->URLSegment . "'>" . $child->Title . "</a></li>";
                } else {
                    $html .= "<li><a href='#'>" . $child->Title . "</a></li>";
                }

                if ($child->Children()->Count() != 0) {
                    $html .= $this->RenderMenu($child);
                }
            }

            $html .= "</ul>";
        }

        return $html;
    }

}