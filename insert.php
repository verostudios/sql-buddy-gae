<?php
/*

SQL Buddy - Web based MySQL administration
http://www.sqlbuddy.com/

insert.php
- insert into a database table

MIT license

2008 Calvin Lough <http://calv.in>

*/

include "functions.php";

loginCheck();

requireDatabaseAndTableBeDefined();

if (isset($db))
	$conn->selectDB($db);

if (isset($table))
	$structureSql = $conn->describeTable($db, $table);

if ($conn->isResultSet($structureSql) && $conn->getAdapter() == "mysql") {
	while ($structureRow = $conn->fetchAssoc($structureSql)) {
		$types[$structureRow['Field']] = $structureRow['Type'];
	}
	$structureSql = $conn->describeTable($db, $table);
}

if ($conn->isResultSet($structureSql) || sizeof($structureSql) > 0) {
	
	if ($_POST) {
		
		$insertFields = "";
		$insertValues = "";
		
		foreach ($_POST as $key=>$value) {
			
			if ($conn->getAdapter() == "sqlite") {
				$insertFields .= $key . ",";
			} else {
				$insertFields .= "`" . $key . "`,";
			}
			
			if (is_array($value)) {
				$value = implode(",", $value);
			}
			
			if (isset($types) && substr($value, 0, 2) == "0x" && isset($binaryDTs) && in_array($types[$key], $binaryDTs)) {
				$insertValues .= $conn->escapeString(urldecode($value)) . ",";
			} else {
				$insertValues .= "'" . $conn->escapeString(urldecode($value)) . "',";
			}
			
		}
		
		$insertFields = substr($insertFields, 0, -1);
		$insertValues = substr($insertValues, 0, -1);
		
		if ($conn->getAdapter() == "sqlite") {
			$insertQuery = "INSERT INTO $table (" . $insertFields . ") VALUES (" . $insertValues . ")";
		} else {
			$insertQuery = "INSERT INTO $db.$table (" . $insertFields . ") VALUES (" . $insertValues . ")";
		}
		
		$conn->query($insertQuery) or ($dbError = $conn->error());
		
		$insertId = $conn->insertId();
		
		if (isset($dbError)) {
			echo '<div class="errormessage" style="margin: 6px 12px 10px; width: 350px">' . $dbError . '</div>';
		} else {
			echo '<div class="insertmessage" id="freshmess">';
			echo __("Your data has been inserted into the database.");
			if ($insertId)
				echo ' (Id: ' . $insertId . ')';
			echo '</div>';
			
			?>
			
			<script type="text/javascript" authkey="<?php echo $requestKey; ?>">
			
			clearPanesOnLoad = true;
			yellowFade($('freshmess'));
			
			</script>
			
			<?php
		}
		
	}
	
	?>
	
	<form id="insertform" onsubmit="submitForm('insertform'); return false">
	<table class="insert" cellspacing="0" cellpadding="0">
	<?php
	
	$firstField = true;
	
	if ($conn->getAdapter() == "sqlite") {
	
		if (sizeof($structureSql) > 0) {
			foreach ($structureSql as $column) {
				
				echo '<tr>';
				echo '<td class="fieldheader"><span style="color: steelblue">';
				if (strpos($column[1], "primary key") > 0) echo '<u>';
				echo $column[0];
				if (strpos($column[1], "primary key") > 0) echo '</u>';
				echo "</span> " . $column[1] . '</td>';
				echo "</tr>";
				echo "<tr>";
				echo '<td class="inputarea">';
				
				if (strpos($column[1], "text") !== false) {
					echo '<textarea name="' . $column[0] . '">';
					if (isset($dbError)) {
						echo $_POST[$column[0]];
					}
					echo '</textarea>';
				} else {
					echo '<input type="text"';
					echo ' name="' . $column[0] . '"';
					if (isset($dbError)) {
						echo 'value="' . $_POST[$column[0]] . '"';
					}
					if ($firstField) {
						echo ' id="FIRSTFIELD"';
						$firstField = false;
					}
					echo ' class="text" />';
				}
				
				?>
				
				</td>
				</tr>
				
				<?php
			}
		}
	
	} else if ($conn->getAdapter() == "mysql") {
		
		if ($conn->isResultSet($structureSql)) {
			while ($structureRow = $conn->fetchAssoc($structureSql)) {
				
				preg_match("/^([a-z]+)(.([0-9]+).)?(.*)?$/", $structureRow['Type'], $matches);
				
				$curtype = $matches[1];
				$cursizeQuotes = $matches[2];
				$cursize = $matches[3];
				$curextra = $matches[4];
				
				echo '<tr>';
				echo '<td class="fieldheader"><span style="color: steelblue">';
				if ($structureRow['Key'] == 'PRI') echo '<u>';
				echo $structureRow['Field'];
				if ($structureRow['Key'] == 'PRI') echo '</u>';
				echo "</span> " . $curtype . $cursizeQuotes . ' ' . $structureRow['Extra'] . '</td>';
				echo "</tr>";
				echo "<tr>";
				echo '<td class="inputarea">';
				if ($structureRow['Type'] == "text") {
					echo '<textarea name="' . $structureRow['Field'] . '">';
					if (isset($dbError))
						echo $_POST[$structureRow['Field']];
					echo '</textarea>';
				}
				elseif (substr($structureRow['Type'], 0, 4) == "enum") {
					$trimmed = substr($structureRow['Type'], 6, -2);
					$listOptions = explode("','", $trimmed);
					echo '<select name="' . $structureRow['Field'] . '">';
					echo '<option> - - - - - </option>';
					foreach ($listOptions as $option) {
						echo '<option value="' . $option . '">' . $option . '</option>';
					}
					echo '</select>';
				}
				elseif (substr($structureRow['Type'], 0, 3) == "set") {
					$trimmed = substr($structureRow['Type'], 5, -2);
					$listOptions = explode("','", $trimmed);
					foreach ($listOptions as $option) {
						$id = $option . rand(1, 1000);
						echo '<label for="' . $id . '"><input name="' . $structureRow['Field'] . '[]" value="' . $option . '" id="' . $id . '" type="checkbox">' . $option . '</label><br />';
					}
				} else {
					echo '<input type="text"';
					echo ' name="' . $structureRow['Field'] . '"';
					if (isset($dbError)) {
						echo 'value="' . $_POST[$structureRow['Field']] . '"';
					}
					if ($firstField && $structureRow['Extra'] != "auto_increment") {
						echo ' id="FIRSTFIELD"';
						$firstField = false;
					}
					echo ' class="text" />';
				}
				
				?>
				
				</td>
				</tr>
				
				<?php
			}
		}
		
	}
	
	?>
	<tr>
	<td style="padding-top: 5px; padding-bottom: 4px">
	<input type="submit" class="inputbutton" value="<?php echo __("Submit"); ?>" />
	</td>
	</tr>
	</table>
	</form>
	
	<?php
	
	if (!$firstField) {
	?>
		<script type="text/javascript" authkey="<?php echo $requestKey; ?>">
		
		$('FIRSTFIELD').focus();
		
		</script>
	<?php
	}

} else {
	?>
	
	<div class="errorpage">
	<h4><?php echo __("Oops"); ?></h4>
	<p><?php printf(__('There was a bit of trouble locating the "%s" table.'), $table); ?></p>
	</div>
	
	<?php
}

?>