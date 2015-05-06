<?php
/**
 * Template for (html) confirmation email message (in Dutch) that may be sent to the subscriber of the interest form.
 *
 */
?>
<html>
	<head>
		<title>Uw belangstelling</title>
		<style>
			th {
				text-align: left;
			}
		</style>
	</head>
	<body>
		<p>
			Bedankt voor uw inschrijving. De volgende gegevens zijn verwerkt:
		</p>

		<table>
		<?php
		foreach( $this->fields as $field_name => $field ) {
			if ( isset( $field ['values'] ) ) {
				?>
				<tr>
					<th><?php echo $field ['label']; ?></th>
					<td><?php echo $field ['values'] [$field ['value']]; ?></td>
				</tr>
				<?php
			} else {
				?>
				<tr>
					<th><?php echo $field ['label']; ?></th>
					<td><?php echo $field ['value']; ?></td>
				</tr>
				<?php
			}
		}
		?>
		</table>

		<p>
			U heeft belangstelling getoond voor de volgende woningtypen:
		</p>
		<ul>
		<?php
		foreach ($this->house_type_model_combis as $htmc => $htmc_arr ) {
			if ( $htmc_arr ['selected'] ) {
			?>
			<li><?php echo $htmc_arr ['htmc'] ['name'] ?></li>
			<?php
			}
		}
		?>
		</ul>
	</body>
</html>
