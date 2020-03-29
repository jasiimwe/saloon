<?php


//check if number is multiple of 5

public function isMultipleOf5($n)
{
	while ($n > 5) {
		$n = $n - 5;

		if($n == 0):
			return true;
		endif;
	return false;

	}
}

?>