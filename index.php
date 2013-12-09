<!DOCTYPE html>
<html>
<head>
        <!--
        Copyright (C) John Ginsberg. For demonstration purposes only.
        // -->
        <title>ToDo demo</title>
        <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
        <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>
		<script src="js/jquery.datetimepicker.js"></script>
		<script src="js/jquery.tinysort.min.js"></script>
        <script src="js/lib/underscore-min.js"></script>
        <script src="js/lib/backbone-min.js"></script>
        <link rel="stylesheet" href="css/styles.css" />
		<link rel="stylesheet" type="text/css" href="css/jquery.datetimepicker.css" />
</head>

<body>

<script id="entryTemplate" type="text/template">
	<div class="links"><span class="edit">Edit</span> <span class="delete">Del</span></div>
	<div class="title"><input type="checkbox"<%= completed == true ? 'checked="checked"' : '' %> value="1" /> <span><%= title %></span><input type="text" class="editTitle" value="<%= title %>" /></div>
	<div class="priority">Priority: <span><%= priority %></span><input type="text" class="editPriority" value="<%= priority %>" /></div>
	<div class="dueBy">Due: <span><%= dueBy %></span><input type="text" class="editDueBy" value="<%= dueBy %>" /></div>
	<div class="details"><span><%= details %></span><textarea class="editDetails"><%= details %></textarea><br /><button class="save">Save</button> <button class="cancel">Cancel</button></div>
</script>

<div id="login">
	<form action="/todo/core/ToDo.php/auth" method="post">
	Login: <input type="text" name="un" /><br />
	Password: <input type="password" name="pw" /> <input type="submit" value="Login" />
	<input type="hidden" name="next" value="redirect" />
	</form>
</div>
<div id="logout">
	<form action="/todo/core/ToDo.php/logout" method="post">
	<input type="submit" value="Logout" />
	<input type="hidden" name="next" value="redirect" />
	</form>
</div>

<script src="js/todo.js"></script>

</body>
</html>