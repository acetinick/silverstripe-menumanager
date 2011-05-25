<% require javascript(menumanager/thirdparty/jquery-jstree/jquery.jstree.js) %>
<% require javascript(menumanager/javascript/MenuManagerAdmin.js) %>
<% require css(menumanager/css/MenuManager.css) %>

<h2>Menus</h2>

<div class="MenuActions">
    $CreateMenuForm
</div>

<div id="ToggleForms">
    <div style="float: left">
        <strong>Menu Actions</strong>
    </div>
    <div style="float: right">
	$CreateMenuItemForm
    </div>
    
    <div style="clear: both;"></div>
</div>

<div id="Workflows" href="$Link(tree)" data-href-sort="$Link(sort)"></div>