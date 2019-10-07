<script>
	Vue.component("LoginForm", {
		props: [
			"default_state",
			"password_policy",
			"reset_hash",
			"remember_disabled",
			"site_title"
		],
		data: function() {
			return {
				alert: "",
				auth_download_message: this.translate('Download the Google Authenticator App (<a href=":ios:" target="_blank">iOS</a>, <a href=":android:" target="_blank">Android</a>) and use it to scan the QR code shown here.', { ':ios': 'https://apps.apple.com/us/app/google-authenticator/id388497605', ':android:': 'https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2' }),
				code: "",
				email: "",
				error: "",
				password: "",
				password_confirm: "",
				state: this.default_state ? this.default_state : "login",
				state_live_area: "Login",
				stay_logged_in: false,
				switch_state_text: "Forgot Password",
				two_factor_secret: "",
				two_factor_token: "",
				two_factor_qr: "",
				two_factor_user: ""
			}
		},
		methods: {
			forgot_password: async function() {
				BigTree.toggle_busy("Requesting password reset");

				let response = await BigTreeAPI.call({
					endpoint: "users/forgot-password",
					method: "POST",
					parameters: {
						email: this.email,
					}
				});

				BigTree.toggle_busy();
				
				this.alert = this.translate("If you entered a correct email address, a link to change your password has been emailed to you.");
			},

			logged_in: async function(data) {
				if (data.multi_domain_key) {
					let no_ssl = (location.protocol !== "https:") ? "no_ssl&" : "";

					for (let key in data.domains) {
						let domain = data.domains[key];

						await $.ajax({
							url: domain + "?" + no_ssl + "bigtree_login_redirect_session_key=" + escape(data.multi_domain_key),
							xhrFields: { withCredentials: true }
						});
					}

					await BigTreeAPI.call({
						"endpoint": "users/remove-login-session",
						"method": "POST",
						"parameters": {
							"session": data.multi_domain_key
						}
					});
				}
				
				window.location.href = data.redirect ? data.redirect : ADMIN_ROOT;
			},
			
			login: async function() {
				BigTree.toggle_busy("Logging in");
				this.error = "";

				let response = await BigTreeAPI.call({
					endpoint: "users/login",
					method: "POST",
					parameters: {
						email: this.email,
						password: this.password,
						stay_logged_in: this.stay_logged_in
					}
				});

				BigTree.toggle_busy();
				
				if (response.logged_in) {
					this.logged_in(response);
				} else if (response.two_factor_auth) {
					this.state = "two_factor_auth";
					this.state_live_area = "Two Factor Authentication";
					this.two_factor_token = response.token;
					this.two_factor_user = response.user;
				} else if (response.two_factor_setup) {
					this.state = "two_factor_setup";
					this.state_live_area = "Two Factor Authentication Setup";
					this.two_factor_secret = response.secret;
					this.two_factor_token = response.token;
					this.two_factor_user = response.user;
					this.two_factor_qr = response.qr_code;
				} else {
					this.error = response.reason;
				}
			},

			reset_password: async function() {
				if (this.password != this.password_confirm) {
					this.error = this.translate("The entered passwords do not match.");

					return;
				}

				let response = await BigTreeAPI.call({
					endpoint: "users/reset-password",
					method: "POST",
					parameters: {
						hash: this.reset_hash,
						password: this.password
					}
				});

				if (response.password_updated) {
					BigTree.growl("Updated Password");
					this.logged_in(response);
				} else {
					this.error = response.reason;
				}
			},
			
			state_switch: function(ev) {
				ev.preventDefault();
				$("#email").focus();
				
				this.alert = this.error = "";

				if (this.state === "login") {
					this.state = "forgot";
					this.state_live_area = "Forgot Password";
					this.switch_state_text = "Back to Login";
				} else {
					this.state = "login";
					this.state_live_area = "Login";
					this.switch_state_text = "Forgot Password";
				}
			},
			
			submit: function(ev) {
				ev.preventDefault();
				
				if (this.state === "login") {
					this.login();
				} else if (this.state === "two_factor_setup") {
					this.two_factor_setup();
				} else if (this.state === "two_factor_auth") {
					this.two_factor_auth();
				} else if (this.state === "forgot") {
					this.forgot_password();
				} else if (this.state === "reset_password") {
					this.reset_password();
				}
			},
			
			two_factor_auth: async function() {
				BigTree.toggle_busy("Verifying authenticator code");
				
				let response = await BigTreeAPI.call({
					endpoint: "users/two-factor-auth",
					method: "POST",
					parameters: {
						code: this.code,
						user: this.two_factor_user,
						token: this.two_factor_token,
						stay_logged_in: this.stay_logged_in
					}
				});
				
				BigTree.toggle_busy();

				if (response.logged_in) {
					this.logged_in(response);
				} else {
					this.error = response.reason;
				}
			},
			
			two_factor_setup: async function() {
				BigTree.toggle_busy("Verifying authenticator code");
				
				let response = await BigTreeAPI.call({
					endpoint: "users/two-factor-setup",
					method: "POST",
					parameters: {
						code: this.code,
						user: this.two_factor_user,
						secret: this.two_factor_secret,
						token: this.two_factor_token,
						stay_logged_in: this.stay_logged_in
					}
				});
				
				BigTree.toggle_busy();
				
				if (response.logged_in) {
					window.location.href = response.redirect ? response.redirect : ADMIN_ROOT;
				} else {
					this.error = response.reason;
				}
			}
		}
	});
</script>

<template>
	<div class="login_form_block">
		<form class="login_form" v-on:submit="submit">
			<h1 class="login_form_title">{{ site_title }}<span class="visually_hide"> {{ translate('Administrative Login') }}</span></h1>

			<fieldset class="login_form_fieldset">
				<div aria-live="assertive" class="visually_hide">{{ translate(state_live_area) }}</div>

				<div v-if="state === 'two_factor_setup'">
					<h2 class="login_form_state">{{ translate('Two-Factor Authentication Setup') }}</h2>

					<div class="login_instructions">
						<div class="login_instruction_step">
							<h3 class="login_instruction_step_title">{{ translate('Step 1') }}</h3>
							<p class="login_instruction_details" v-html="auth_download_message"></p>
						</div>
						<img class="login_auth_image" :src="two_factor_qr" :alt="translate('QR Code')">
					</div>

					<div class="login_instruction_step">
						<h3 class="login_instruction_step_title">{{ translate('Step 2') }}</h3>
						<p class="login_instruction_details">{{ translate('Enter the code shown in the app in the field below.') }}</p>
					</div>
				</div>

				<div v-if="state === 'two_factor_auth'">
					<h2 class="login_form_state">{{ translate('Two-Factor Authentication') }}</h2>
					<p class="login_instruction_details">{{ translate('Enter the code shown in your Google Authenticator app in the field below.') }}</p>
				</div>

				<div v-if="state === 'reset_password'">
					<h2 class="login_form_state">{{ translate('Set a New Password') }}</h2>
					<h3 v-if="password_policy" class="login_instruction_step_title">{{ translate('Requirements') }}</h3>
					<div class="login_instruction_details" v-html="password_policy"></div>
				</div>
				
				<div v-if="error" class="error_message">
					<div class="error_message_header">
						<icon wrapper="error_message" icon="warning"></icon>
						<span class="error_message_title">{{ error }}</span>
					</div>
				</div>
				
				<div v-if="alert" class="error_message orange">
					<div class="error_message_header">
						<icon wrapper="error_message" icon="warning"></icon>
						<span class="error_message_title">{{ alert }}</span>
					</div>
				</div>

				<h2 v-if="state === 'forgot'" class="login_form_state">Forgot Password</h2>
				
				<div v-if="state === 'two_factor_setup' || state === 'two_factor_auth'" class="field">
					<div class="field_header">
						<div class="field_header_group">
							<label class="field_title" for="code">{{ translate('Authenticator Code') }}</label>
						</div>
					</div>
					<div class="field_text">
						<input class="field_input" id="code" name="code" type="number" v-model="code">
					</div>
				</div>
				
				<div v-if="state === 'login' || state === 'forgot'" class="field">
					<div class="field_header">
						<div class="field_header_group">
							<label class="field_title" for="email">{{ translate('Email Address') }}</label>
						</div>
					</div>
					<div class="field_text">
						<input class="field_input" id="email" name="email" type="email" v-model="email">
					</div>
				</div>
				
				<div v-if="state === 'login' || state === 'reset_password'" class="field">
					<div class="field_header">
						<div class="field_header_group">
							<label for="password" class="field_title">Password</label>
						</div>
					</div>
					<div class="field_text">
						<input class="field_input" id="password" name="password" type="password" v-model="password">
					</div>
				</div>

				<div v-if="state === 'reset_password'" class="field">
					<div class="field_header">
						<div class="field_header_group">
							<label for="password_confirm" class="field_title">Confirm Password</label>
						</div>
					</div>
					<div class="field_text">
						<input class="field_input" id="password_confirm" name="password_confirm" type="password"
							   v-model="password_confirm">
					</div>
				</div>
				
				<div v-if="state === 'login' && !remember_disabled" class="field">
					<div class="field_choices">
						<div class="field_choice">
							<input class="field_choice_input" id="remember" name="remember" type="checkbox"
								   v-model="stay_logged_in">
							<span class="field_choice_indicator field_choice_indicator_checkbox"></span>
							<label class="field_choice_label" for="remember">{{ translate('Stay Logged In') }}</label>
						</div>
					</div>
				</div>
				
				<div class="field">
					<input class="field_button" type="submit" :value="translate(state === 'login' ? 'Login' : 'Submit')" />
					<a v-if="state === 'login' || state === 'forgot'" v-on:click="state_switch"
					   href="#" class="login_forgot_password">{{ translate(switch_state_text) }}</a>
				</div>
			</fieldset>
		</form>
		<div class="login_form_footer">
			<icon wrapper="login_form_logo" icon="logo"></icon>
			<a class="login_form_link" href="https://www.bigtreecms.org/" target="_blank">BigTree CMS</a>
		</div>
	</div>
</template>