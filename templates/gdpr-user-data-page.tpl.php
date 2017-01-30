<pre>
<h2>Basic data we collect:</h2>
<?php print_r($raw_user_data); ?>
<h2>Extra fields in your user profile:</h2>
<?php print_r($raw_field_values); ?>
<?php $formatted_content = implode(', ', $formatted_content);?>
<h2>And this is all the content you have created:</h2>
<?php print $formatted_content; ?>
</pre>