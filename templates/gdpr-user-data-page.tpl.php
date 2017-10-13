<h2><?php print t('Data stored about you') ?>:</h2>

<table id="gdpr_user_data">
  <?php foreach ($user_data as $field => $value): ?>
      <tr>
          <th><?php print $field ?></th>
          <td><?php print $value ?></td>
      </tr>
  <?php endforeach ?>
</table>
