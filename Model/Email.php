<?php

class Email extends RunUnit {

	public $errors = array();
	public $id = null;
	public $session = null;
	public $unit = null;
	private $mail_sent = false;
	private $body = null;
	protected $body_parsed = null;
	private $account_id = null;
	private $images = array();
	private $subject = null;
	private $recipient_field;
	private $recipient;
	private $html = 1;
	public $icon = "fa-envelope";
	public $type = "Email";
	private $subject_parsed = null;

	public function __construct($fdb, $session = null, $unit = null, $run_session = NULL) {
		parent::__construct($fdb, $session, $unit, $run_session);

		if ($this->id):
			$vars = $this->dbh->findRow('survey_emails', array('id' => $this->id));
			if ($vars):
				$this->account_id = $vars['account_id'];
				$this->recipient_field = $vars['recipient_field'];
				$this->body = $vars['body'];
				$this->body_parsed = $vars['body_parsed'];
				$this->subject = $vars['subject'];
//				$this->html = $vars['html'] ? 1:0;
				$this->html = 1;

				$this->valid = true;
			endif;
		endif;
	}

	public function create($options) {
		if (!$this->id) {
			$this->id = parent::create('Email');
		} else {
			$this->modify($options);
		}

		$parsedown = new ParsedownExtra();
		if (isset($options['body'])) {
			$this->recipient_field = $options['recipient_field'];
			$this->body = $options['body'];
			$this->subject = $options['subject'];
			if (isset($options['account_id']) AND is_numeric($options['account_id'])) {
				$this->account_id = (int) $options['account_id'];
			}
//			$this->html = $options['html'] ? 1:0;
			$this->html = 1;
		}
		if ($this->account_id === null):
			$email_accounts = $this->getEmailAccounts();
			if (count($email_accounts) > 0):
				$this->account_id = current($email_accounts)['id'];
			endif;
		endif;


		$this->body_parsed = $parsedown->text($this->body);

		$this->dbh->insert_update('survey_emails', array(
			'id' => $this->id,
			'account_id' => $this->account_id,
			'recipient_field' => $this->recipient_field,
			'body' => $this->body,
			'body_parsed' => $this->body_parsed,
			'subject' => $this->subject,
			'html' => $this->html,
		));

		$this->valid = true;

		return true;
	}

	private function getSubject() {
		if ($this->subject_parsed === NULL):
			if ($this->knittingNeeded($this->subject)):
				if ($this->session_id):
					$this->subject_parsed = $this->getParsedText($this->subject);
				else:
					$this->subject_parsed = $this->getParsedTextAdmin($this->subject);
				endif;
			else:
				return $this->subject;
			endif;
		endif;
		return $this->subject_parsed;
	}

	private function getBody($embed_email = true) {

		if (isset($this->run_name)) {
			$sess = isset($this->session) ? $this->session : "TESTCODE";
			$login_link = site_url("{$this->run_name}?code=".urlencode($sess));
		} else {
			$login_link = site_url();
			alert("Generated a login link, but no run was specified", 'alert-danger');
		}

		if ($this->html) {
			$login_link = "<a href='$login_link'>Login link</a>";

			if ($this->session_id):
				$response = $this->getParsedBody($this->body, true);
				if (isset($response['body'])):
					$this->body_parsed = $response['body'];
				endif;
				if (isset($response['images'])):
					$this->images = $response['images'];
				endif;
			else: // admin stuff
				if ($embed_email):
					if (!$this->grabRandomSession()) {
						return false;
					}
					$response = $this->getParsedBody($this->body, $embed_email);
					if (isset($response['body'])):
						$this->body_parsed = $response['body'];
					endif;
					if (isset($response['images'])):
						$this->images = $response['images'];
					endif;
				else:
					$response = $this->getParsedBodyAdmin($this->body, $embed_email);
					return $response;
				endif;
			endif;

			$this->body_parsed = str_replace("{{login_link}}", $login_link, $this->body_parsed);
			$this->body_parsed = str_replace("{{login_code}}", $this->session, $this->body_parsed);
			return $this->body_parsed;
		} else {
			$this->body = str_replace("{{login_link}}", $login_link, $this->body);
			$this->body = str_replace("{{login_code}}", $this->session, $this->body);
			return $this->body;
		}
	}

	private function getEmailAccounts() {
		global $user;
		return $this->dbh->select('id, from')
				->from('survey_email_accounts')
				->where(array('user_id' => $user->id))->fetchAll();
	}

	public function displayForRun($prepend = '') {
		$email_accounts = $this->getEmailAccounts();

		if (!empty($email_accounts)):
			$dialog = '<p><label>Account: <br>
			<select class="select2" name="account_id" style="width:350px">
			<option value=""></option>';
			foreach ($email_accounts as $acc):
				if (isset($this->account_id) AND $this->account_id == $acc['id'])
					$dialog .= "<option selected value=\"{$acc['id']}\">{$acc['from']}</option>";
				else
					$dialog .= "<option value=\"{$acc['id']}\">{$acc['from']}</option>";
			endforeach;
			$dialog .= "</select>";
			$dialog .= '</label></p>';
		else:
			$dialog = "<h5>No email accounts. <a href='" . WEBROOT . "admin/mail/" . "'>Add some here.</a></h5>";
		endif;
		$dialog .= '<p><label>Subject: <br>
			<input class="form-control full_width" type="text" placeholder="Email subject" name="subject" value="' . h($this->subject) . '">
		</label></p>
		<p><label>Recipient-Field: <br>
					<input class="form-control full_width" type="text" placeholder="survey_users$email" name="recipient_field" value="' . h($this->recipient_field) . '">
				</label></p>
		<p><label>Body: <br>
			<textarea style="width:388px;"  data-editor="markdown" placeholder="You can use Markdown" name="body" rows="7" cols="60" class="form-control col-md-5">' . h($this->body) . '</textarea></label><br>
			<code>{{login_link}}</code> will be replaced by a personalised link to this run, <code>{{login_code}}</code> will be replaced with this user\'s session code.</p>';
//		<p><input type="hidden" name="html" value="0"><label><input type="checkbox" name="html" value="1"'.($this->html ?' checked ':'').'> send HTML emails (may worsen spam rating)</label></p>';
		$dialog .= '<p class="btn-group"><a class="btn btn-default unit_save" href="ajax_save_run_unit?type=Email">Save.</a>
		<a class="btn btn-default unit_test" href="ajax_test_unit?type=Email">Test</a></p>';

		$dialog = $prepend . $dialog;
		return parent::runDialog($dialog, 'fa-envelope');
	}

	public function getRecipientField() {
		if (empty($this->recipient_field)) {
			$this->recipient_field = 'survey_users$email';
		}

		$opencpu_vars = $this->getUserDataInRun($this->dataNeeded($this->dbh, $this->recipient_field));
		$result = opencpu_evaluate($this->recipient_field, $opencpu_vars, 'json');

		return $result;
	}

	public function sendMail($who = NULL) {
		if ($who === null):
			$this->recipient = $this->getRecipientField();
		else:
			$this->recipient = $who;
		endif;

		if ($this->recipient == null):
			//formr_log("Email recipient could not be determined from this field definition " . $this->recipient_field);
			alert("We could not find an email recipient.", 'alert-danger');
			$this->mail_sent = false;
			return false;
		endif;

		if ($this->account_id === null):
			alert("The study administrator (you?) did not set up an email account. <a href='" . WEBROOT . "/admin/mail/'>Do it now</a> and then select the account in the email dropdown.", 'alert-danger');
			$this->mail_sent = false;
			return false;
		endif;

		global $user;
		if($user->getEmail() !== $this->recipient) {

			$mails_sent = $this->numberOfEmailsSent();
			if ($mails_sent['in_last_1m'] > 0):
				alert(sprintf("We already sent %d mail to this recipient in the last minute. No email was sent.", $mails_sent['in_last_1m']), 'alert-warning');
				return false;
			elseif ($mails_sent['in_last_10m'] > 0):
				alert(sprintf("We already sent %d mail to this recipient in the last 10 minutes. No email was sent.", $mails_sent['in_last_10m']), 'alert-warning');
				return false;
			elseif ($mails_sent['in_last_1h'] > 2):
				alert(sprintf("We already sent %d mails to this recipient in the last hour. No email was sent.", $mails_sent['in_last_1h']), 'alert-warning');
				return false;
			elseif ($mails_sent['in_last_1d'] > 5):
				alert(sprintf("We already sent %d mails to this recipient in the last day. No email was sent.", $mails_sent['in_last_1d']), 'alert-warning');
				return false;
			elseif ($mails_sent['in_last_1w'] > 20):
				alert(sprintf("We already sent %d mails to this recipient in the last week. No email was sent.", $mails_sent['in_last_1w']), 'alert-warning');
				return false;
			endif;
		}

		$acc = new EmailAccount($this->dbh, $this->account_id, null);
		$mail = $acc->makeMailer();

//		if($this->html)
		$mail->IsHTML(true);

		$mail->AddAddress($this->recipient);
		$mail->Subject = $this->getSubject();
		$mail->Body = $this->getBody();
		foreach ($this->images AS $image_id => $image):
			$local_image = INCLUDE_ROOT . 'tmp/' . uniqid() . $image_id;
			copy($image, $local_image);
			register_shutdown_function(create_function('', "unlink('{$local_image}');"));

			if (!$mail->AddEmbeddedImage($local_image, $image_id, $image_id, 'base64', 'image/png' )) {
				$this->mail_sent = false;
				alert('Email with the subject ' . $this->subject . ' was not sent to ' . $this->recipient . ':<br>' . $mail->ErrorInfo, 'alert-danger');
			}
		endforeach;

		if (!$mail->Send()) {
			$this->mail_sent = false;
			alert('Email with the subject ' . $this->subject . ' was not sent to ' . $this->recipient . ':<br>' . $mail->ErrorInfo, 'alert-danger');
		} else {
			$this->mail_sent = true;
			$this->logMail();
		}
	}

	private function numberOfEmailsSent() {
		$log = $this->dbh->prepare("SELECT
			SUM(created > DATE_SUB(NOW(), INTERVAL 1 MINUTE)) AS in_last_1m,
			SUM(created > DATE_SUB(NOW(), INTERVAL 10 MINUTE)) AS in_last_10m,
			SUM(created > DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS in_last_1h,
			SUM(created > DATE_SUB(NOW(), INTERVAL 1 DAY)) AS in_last_1d,
			SUM(1) AS in_last_1w
			FROM `survey_email_log`
			WHERE recipient = :recipient AND created > DATE_SUB(NOW(), INTERVAL 7 DAY)");
		$log->bindParam(':recipient', $this->recipient);
		$log->execute();
		return $log->fetch(PDO::FETCH_ASSOC);
	}

	private function logMail() {
		$query = "INSERT INTO `survey_email_log` (session_id, email_id, created, recipient) VALUES (:session_id, :email_id, NOW(), :recipient)";
		$this->dbh->exec($query, array(
			'session_id' => $this->session_id,
			'email_id' => $this->id,
			'recipient' => $this->recipient,
		));
	}

	public function test() {
		$results = $this->getSampleSessions();
		if (!$results) {
			echo 'No data to compare to yet.';
			return false;
		}
		
		$RandReceiv = crypto_token(9, true);
		$receiver = $RandReceiv . '@mailinator.com';

		$this->sendMail($receiver);
		$link = "https://mailinator.com/inbox.jsp?to=".$RandReceiv;

		echo "<h4>" . $this->getSubject() . "</h4>";
		echo "<p><a href='$link'>Check whether the email arrived properly at a random email address on Mailinator.com</a></p>";

		echo $this->getBody(false);

		if ($this->recipient_field === null OR trim($this->recipient_field) == '') {
			$this->recipient_field = 'survey_users$email';
		}

		$output = '
			<table class="table table-striped">
				<thead>
					<tr>
						<th>Code (Position)</th>
						<th>Test</th>
					</tr>
				</thead>
				<tbody>%s</tbody>
			</table>';

		$rows = '';
		foreach ($results AS $row):
			$this->run_session_id = $row['id'];

			$opencpu_vars = $this->getUserDataInRun($this->dataNeeded($this->dbh, $this->recipient_field));
			$email = stringBool(opencpu_evaluate($this->recipient_field, $opencpu_vars, 'json'));
			$good = filter_var($email, FILTER_VALIDATE_EMAIL) ? '' : 'text-warning';
			$rows .= "
				<tr>
					<td style='word-wrap:break-word;max-width:150px'><small>" . $row['session'] . " ({$row['position']})</small></td>
					<td class='$good'>" . $email . "</td>
				</tr>";
		endforeach;

		echo sprintf($output, $rows);
		$this->run_session_id = null;
	}

	public function exec() {
		$err = $this->sendMail();
		if ($this->mail_sent):
			$this->end();
			return false;
		else:
			return array('body' => $err);
		endif;
	}

}
