<script>
	Vue.component("FieldTypeAddress", {
		extends: FieldType,
		data: function() {
			return {
				city: (typeof this.value == "object") ? this.value.city : "",
				country: (typeof this.value == "object") ? this.value.country : "United States",
				state: (typeof this.value == "object") ? this.value.state : "",
				province: (typeof this.value == "object") ? this.value.state : "",
				street: (typeof this.value == "object") ? this.value.street : "",
				zip: (typeof this.value == "object") ? this.value.zip : "",
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
		},
		calculated: {
			current_value: function() {
				return {
					street: this.street,
					city: this.city,
					state: (this.country === "United States") ? this.state : this.province,
					zip: this.zip,
					country: this.country
				};
			}
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true" :required="required" :error="error">
		<div class="field_address">
			<div class="field_group">
				<div class="field_wrapper">
					<label class="field_label" :for="'field_street_' + uid">{{ translate("Street Address") }}</label>
					<input class="field_input" :id="'field_street_' + uid" :name="name + '[street]'"
						   v-model="street" :placeholder="translate('Street')" type="text" :required="required">
				</div>
			</div>
			
			<div class="field_group">
				<div class="field_wrapper">
					<label class="field_label" :for="'field_city_' + uid">{{ translate("City") }}</label>
					<input class="field_input" :id="'field_city_' + uid" :name="name + '[city]'" :required="required"
						   :placeholder="translate('City')" type="text" v-model="city">
				</div>
				
				<div v-if="country === 'United States'" class="field_wrapper">
					<label class="field_label" :for="'field_state_' + uid">{{ translate("State") }}</label>
					<select class="field_select" :id="'field_state_' + uid" :name="name + '[state]'"
							:required="required" v-model="state">
						<option value="">{{ translate('Select a State') }}</option>
						<option v-for="(item, abbreviation) in this.states" :value="abbreviation">{{ item }}</option>
					</select>
					<icon icon="arrow_drop_down" wrapper="field_select"></icon>
				</div>
				
				<div v-else class="field_wrapper">
					<label class="field_label" :for="'field_state_' + uid">{{ translate("State or Province") }}</label>
					<input class="field_input" :id="'field_state_' + uid" :name="name + '[state]'" v-model="province"
						   :placeholder="translate('State / Province')" type="text" :required="required" >
				</div>
			</div>
			
			<div class="field_group">
				<div class="field_wrapper">
					<label class="field_label" :for="'field_zip_' + uid">
						{{ translate(country === "United States" ? "Zip Code" : "Zip or Postal Code") }}
					</label>
					<input class="field_input" :id="'field_zip_' + uid" :name="name + '[zip]'" v-model="zip"
						   type="text" :required="required"
						   :placeholder="translate(country === 'United States' ? 'Zip Code' : 'Zip / Postal Code')">
				</div>
				
				<div class="field_wrapper">
					<label class="field_label" :for="'field_country_' + uid">{{ translate("Country") }}</label>
					<select class="field_select" :id="'field_country_' + uid" :name="name + '[country]'"
							:required="required" v-model="country">
						<option value="">{{ translate('Select a Country') }}</option>
						<option v-for="item in this.countries">{{ item }}</option>
					</select>
					<icon icon="arrow_drop_down" wrapper="field_select"></icon>
				</div>
			</div>
		</div>
	</field>
</template>