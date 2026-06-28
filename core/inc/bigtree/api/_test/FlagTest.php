<?php
	use BigTree\Api\Flag;

	function test_flag_checkbox_truthy_to_on() {
		T::equals(Flag::checkbox(true), "on", "bool true → on");
		T::equals(Flag::checkbox(1), "on", "int 1 → on");
		T::equals(Flag::checkbox("on"), "on", "non-empty string → on");
		T::equals(Flag::checkbox("yes"), "on", "any truthy string → on");
	}

	function test_flag_checkbox_falsy_to_empty() {
		T::equals(Flag::checkbox(false), "", "bool false → empty");
		T::equals(Flag::checkbox(null), "", "null → empty");
		T::equals(Flag::checkbox(0), "", "int 0 → empty");
		T::equals(Flag::checkbox(""), "", "empty string → empty");
		T::equals(Flag::checkbox("0"), "", "string zero → empty (mirrors !empty)");
	}

	function test_flag_is_on() {
		T::equals(Flag::isOn("on"), true, "exactly \"on\" → true");
		T::equals(Flag::isOn(""), false, "empty string → false");
		T::equals(Flag::isOn(null), false, "null → false");
		T::equals(Flag::isOn("On"), false, "case-sensitive: \"On\" → false");
		T::equals(Flag::isOn(1), false, "int 1 → false (strict)");
		T::equals(Flag::isOn("yes"), false, "other truthy string → false");
	}

	function test_flag_round_trips_with_checkbox() {
		T::equals(Flag::isOn(Flag::checkbox(true)), true, "checkbox(true) reads back on");
		T::equals(Flag::isOn(Flag::checkbox(false)), false, "checkbox(false) reads back off");
	}
