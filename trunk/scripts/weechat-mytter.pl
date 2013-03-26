use LWP::UserAgent;
use Data::Dumper;

# Create a user agent object
use LWP::UserAgent;
my $ua = LWP::UserAgent->new;
$ua->agent("LWP ");

# Create a request
my $req = HTTP::Request->new(POST => 'https://USER:PASS@HOST/mytter/1.1/statuses/update.json');
$req->content_type('application/x-www-form-urlencoded');

sub processmsg {
	my ( $data, $buffer, $date, $tags, $displayed, $highlight, $prefix, $message) = @_;
	  # get servername from buffer
	my $infolist = weechat::infolist_get("buffer",$buffer,"");
	weechat::infolist_next($infolist);
	my (undef, $buffername) = split( /\./, weechat::infolist_string($infolist,"name") );
	weechat::infolist_free($infolist);
	
	#prefix: " *"
	if (($data eq "message" and $highlight eq "1") or ($data eq "" and $highlight eq "0")) {
		$message =~ s/&/%26/g;
		if ($prefix ne " *") {
			$prefix = "$prefix:";
		}
		if ("$buffername:" eq $prefix) {
			$prefix = "";
		}
		$req->content("status=$buffername: $prefix $message");

		# Pass request to the user agent and get a response back
		my $res = $ua->request($req);
	}
	return weechat::WEECHAT_RC_OK;
}

weechat::register("mytter", "brimstone", "0.1", "GPL3", "Script to submit highlights and PMs to mytter", "", "");
weechat::hook_print("", "notify_message", "", 1, "processmsg", "message");
weechat::hook_print("", "notify_private", "", 1, "processmsg", "");
weechat::hook_print("", "notify_highlight", "", 1, "processmsg", "");
