<script>
	Vue.component("field-address", {
		props: [
			"title",
			"subtitle",
			"name",
			"value",
			"required"
		],
		
		data: function() {
			return {
				current_country: (typeof this.value == "object") ? this.value.country : "United States",
				countries: [
					"Afghanistan","Albania","Algeria","Andorra","Angola","Anguilla","Antigua & Barbuda",
					"Argentina","Armenia","Aruba","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh",
					"Barbados","Belarus","Belgium","Belize","Benin","Bermuda","Bhutan","Bolivia",
					"Bosnia & Herzegovina","Botswana","Brazil","British Virgin Islands","Brunei","Bulgaria",
					"Burkina Faso","Burundi","Cambodia","Cameroon","Canada","Cape Verde","Cayman Islands","Chad",
					"Chile","China","Colombia","Congo","Cook Islands","Costa Rica","Cote D Ivoire","Croatia","Cuba",
					"Cyprus","Czech Republic","Denmark","Djibouti","Dominica","Dominican Republic","Ecuador","Egypt",
					"El Salvador","Equatorial Guinea","Estonia","Ethiopia","Falkland Islands","Faroe Islands","Fiji",
					"Finland","France","French Polynesia","French West Indies","Gabon","Gambia","Georgia","Germany",
					"Ghana","Gibraltar","Greece","Greenland","Grenada","Guam","Guatemala","Guernsey","Guinea",
					"Guinea Bissau","Guyana","Haiti","Honduras","Hong Kong","Hungary","Iceland","India","Indonesia",
					"Iran","Iraq","Ireland","Isle of Man","Israel","Italy","Jamaica","Japan","Jersey","Jordan",
					"Kazakhstan","Kenya","Kuwait","Kyrgyz Republic","Laos","Latvia","Lebanon","Lesotho","Liberia",
					"Libya","Liechtenstein","Lithuania","Luxembourg","Macau","Macedonia","Madagascar","Malawi",
					"Malaysia","Maldives","Mali","Malta","Mauritania","Mauritius","Mexico","Moldova","Monaco",
					"Mongolia","Montenegro","Montserrat","Morocco","Mozambique","Namibia","Nepal","Netherlands",
					"Netherlands Antilles","New Caledonia","New Zealand","Nicaragua","Niger","Nigeria","Norway","Oman",
					"Pakistan","Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Poland",
					"Portugal","Puerto Rico","Qatar","Reunion","Romania","Russia","Rwanda","Saint Pierre & Miquelon",
					"Samoa","San Marino","Satellite","Saudi Arabia","Senegal","Serbia","Seychelles","Sierra Leone",
					"Singapore","Slovakia","Slovenia","South Africa","South Korea","Spain","Sri Lanka",
					"St Kitts & Nevis","St Lucia","St Vincent","St. Lucia","Sudan","Suriname","Swaziland","Sweden",
					"Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand","Timor L'Este","Togo","Tonga",
					"Trinidad & Tobago","Tunisia","Turkey","Turkmenistan","Turks & Caicos","Uganda","Ukraine",
					"United Arab Emirates","United Kingdom","United States","United States Minor Outlying Islands",
					"Uruguay","Uzbekistan","Venezuela","Vietnam","Virgin Islands (US)","Yemen","Zambia","Zimbabwe"
				],
				states: {
					"AL": "Alabama",
					"AK": "Alaska",
					"AS": "American Samoa",
					"AZ": "Arizona",
					"AR": "Arkansas",
					"CA": "California",
					"CO": "Colorado",
					"CT": "Connecticut",
					"DE": "Delaware",
					"DC": "District Of Columbia",
					"FM": "Federated States Of Micronesia",
					"FL": "Florida",
					"GA": "Georgia",
					"GU": "Guam",
					"HI": "Hawaii",
					"ID": "Idaho",
					"IL": "Illinois",
					"IN": "Indiana",
					"IA": "Iowa",
					"KS": "Kansas",
					"KY": "Kentucky",
					"LA": "Louisiana",
					"ME": "Maine",
					"MH": "Marshall Islands",
					"MD": "Maryland",
					"MA": "Massachusetts",
					"MI": "Michigan",
					"MN": "Minnesota",
					"MS": "Mississippi",
					"MO": "Missouri",
					"MT": "Montana",
					"NE": "Nebraska",
					"NV": "Nevada",
					"NH": "New Hampshire",
					"NJ": "New Jersey",
					"NM": "New Mexico",
					"NY": "New York",
					"NC": "North Carolina",
					"ND": "North Dakota",
					"MP": "Northern Mariana Islands",
					"OH": "Ohio",
					"OK": "Oklahoma",
					"OR": "Oregon",
					"PW": "Palau",
					"PA": "Pennsylvania",
					"PR": "Puerto Rico",
					"RI": "Rhode Island",
					"SC": "South Carolina",
					"SD": "South Dakota",
					"TN": "Tennessee",
					"TX": "Texas",
					"UT": "Utah",
					"VT": "Vermont",
					"VI": "Virgin Islands",
					"VA": "Virginia",
					"WA": "Washington",
					"WV": "West Virginia",
					"WI": "Wisconsin",
					"WY": "Wyoming"
				}
			}
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true">
		<div class="field_name">
			<div class="field_group">
				<div class="field_wrapper">
					<label class="field_label" :for="'field_street_' + this._uid">{{ translate("Street Address") }}</label>
					<input class="field_input" :id="'field_street_' + this._uid" :name="name + '[street]'"
						   :value="value.street" :placeholder="translate('Street')" type="text" :required="required">
				</div>
			</div>
			
			<div class="field_group">
				<div class="field_wrapper">
					<label class="field_label" :for="'field_city_' + this._uid">{{ translate("City") }}</label>
					<input class="field_input" :id="'field_city_' + this._uid" :name="name + '[city]'"
						   :value="value.city" :placeholder="translate('City')" type="text" :required="required">
				</div>
				
				<div v-if="current_country === 'United States'" class="field_wrapper">
					<label class="field_label" :for="'field_state_' + this._uid">{{ translate("State") }}</label>
					<select class="field_select" :id="'field_state_' + this._uid" :name="name + '[state]'"
							:required="required">
						<option value="">{{ translate('Select a State') }}</option>
						<option v-for="abbreviation, state in this.states" :selected="value.state === abbreviation">{{ state }}</option>
					</select>
					<icon icon="arrow_drop_down" wrapper="field_select"></icon>
				</div>
				
				<div v-else class="field_wrapper">
					<label class="field_label" :for="'field_state_' + this._uid">{{ translate("State or Province") }}</label>
					<input class="field_input" :id="'field_state_' + this._uid" :name="name + '[state]'"
						   :value="value.state" :placeholder="translate('Street / Province')" type="text" :required="required">
				</div>
			</div>
			
			<div class="field_group">
				<div class="field_wrapper">
					<label class="field_label" :for="'field_zip_' + this._uid">{{ translate(current_country === "United States" ? "Zip Code" : "Zip or Postal Code") }}</label>
					<input class="field_input" :id="'field_zip_' + this._uid" :name="name + '[zip]'"
						   :value="value.zip" :placeholder="translate(current_country === 'United States' ? 'Zip Code' : 'Zip / Postal Code')" type="text" :required="required">
				</div>
				
				<div class="field_wrapper">
					<label class="field_label" :for="'field_country_' + this._uid">{{ translate("Country") }}</label>
					<select class="field_select" :id="'field_country_' + this._uid" :name="name + '[country]'"
							:required="required" v-model="current_country">
						<option value="">{{ translate('Select a Country') }}</option>
						<option v-for="country in this.countries" :selected="value.country === country">{{ country }}</option>
					</select>
					<icon icon="arrow_drop_down" wrapper="field_select"></icon>
				</div>
			</div>
		</div>
	</field>
</template>