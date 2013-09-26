[Blog Eintrag](http://blog.kanedo.net/1306,gitlab-4-1-auf-einem-uberspace-installieren.html?pk_campaign=Tutorial&pk_kwd=github)
Gitlab ist in Version 4.1 verfügbar und nun möchte der geeignete Uberspace-Nutzer natürlich auch die aktuelle Version verwenden.  
Leider birgt das immer gewisse Schwierigkeiten, da Gitlab nicht auf Shared-Hosting ausgelegt ist und vor allem exzesiv gebrauch von `sudo` macht.
Nunja trotzdem habe ich es zum laufen gebracht. Die [Schwierigkeit mit dem Apachen](http://blog.kanedo.net/1298,gitlab-4-1-und-der-apache.html?pk_campaign=Tutorial&pk_kwd=github) wurden von [Jan-Henrik souverän gelößt](http://blog.kanedo.net/1298,gitlab-4-1-und-der-apache.html#comment-4939) (Herzlichen Dank noch einmal!)  
Nun aber zum eigentlichen Setup:  

##Vorbereitungen
###Falls etwas mit dem SSH unterwegs schiefgeht
und zum Beispiel die Meldung kommt, dass man ja gar kein User ist (da Antwortet gitolite). Mann kann sich immer noch ohne Key anmelden mittels:

	ssh user@host -o PubkeyAuthentication=no
	
Zunächst müssen ein paar Dinge vorbereitet werden. Manche davon könnt ihr überspringen, wenn die Installation schon geschehen ist.

###Ruby 1.9.3 installieren
	rvm install 1.9.3

###Rubygems installieren
	rvm install rubygems 1.8.25
Bei einem Fehler hilft in der Regel

	rvm install rubygems 1.8.25 --verify-downloads 1
	
###Python2 au Python2.7 legen
Gitlab benötigt Python2.7 unter dem Alias `python2`. Dafür fügt ihr die Zeile

	alias python2=python2.7
	
ans Ende der `~/.bash_profile` ein.
###Redis installieren
	toast arm redis
Legt eine `conf` Datei in `~/.redis` an (Der Ordner exisitert möglicherweise nicht; anlegen). In diese Datei schreibt ihr dann:

	unixsocket /home/<euer uberspace name>/.redis/sock
	daemonize no
	logfile /home/<euer uberspace name>/.redis/log
	port 0
###Bundler installieren
Nun noch den Bundler installieren:

	gem install bundler

##Gitolite
Als nächstes muss die Version 3.2 von gitolite installiert werden. Wer schon gitolite installiert hat, macht am besten ein Backup und deinstalliert die alte Version mittels der[ Anleitung im Dokuwiki](https://uberspace.de/dokuwiki/cool:gitolite#deinstallation) Die Repos dürfen liegen bleiben!  
###Version 3.2 aus dem Repo clonen und installieren
	 git clone -b gl-v320 https://github.com/gitlabhq/gitolite.git ./gitolite  
	 gitolite/install -ln ~/bin  
	 
###SSH-Key für den Admin generieren
	ssh-keygen -f admin
###Gitolite instalieren
	gitolite setup -pk admin.pub  
	mv admin .ssh/id_rsa  
	mv admin.pub .ssh/id_rsa.pub
Jetzt unbedingt testen, ob man Admin von gitolite ist (sonst nicht weitermachen!):  
	
	git clone <user>@<user>.<host>.uberspace.de:gitolite-admin.git
	
Wenn das geklappt hat, den Admin wieder löschen:

	rm -rf gitolite-admin/

###Gitolite Rechte
	chmod 750 ~/.gitolite  
	chmod -R ug+rwXs,o-rwx ~/repositories/

##Gitlab installieren
Jetzt gitlab wirklich installieren. Leider muss man ein paar Dinge nicht nur in den Konfigurationsdateien anpassen, deswegen habe ich einen Patch auf Github gestellt. In diesem [Repository liegen auch die Skripte für die daemontools-services](https://github.com/kanedo/Gitlab-Uberspace)

	git clone https://github.com/gitlabhq/gitlabhq.git gitlab  
	cd gitlab  
	git checkout stable  
Jetzt den Patch anwenden:

	curl https://raw.github.com/kanedo/Gitlab-Uberspace/master/uberspace.patch -o uberspace.patch  
	git apply --check uberspace.patch
	
###Konfiguration
	cp config/gitlab.yml.example config/gitlab.yml
	nano config/gitlab.yml
In dieser Datei müssen verschiedene Parameter angepasst werden. Die Liste ist in folgendem Format: `Zeile:Key,Beschreibung`

* *18*  : **host**, Der Domainname unter dem Gitlab laufen soll
* *26*  : **user**, Dein Uberspace Account
* *99*  : **path**, Pfad zu den gitlab sattelites (noch keine Idee was das ist, ich habe nur den Pfad so angepasst, dass er nun mein home-Verzeichniss als Stamm hat)
* *108* : **admin_uri**, SSH-Pfad zum auschecken des Gitolite-Admin-Repositories (Siehe [Gitolite installieren](#gitolite_instalieren))
* *110* : **repos_path**, Pfad zu den Repositories (meist /home/<user>/repositories)
* *111* : **hooks_path**, Pfad zu den Gitolite-Hooks (Hier den Home-Pfad durch euren ersetzen)
* *112* : **admin_key**, Name des Admin-Keys, in unserem Fall `admin`
* *115* : **admin_user**, Uberspace-Name

###Unicorn
	cp config/unicorn.rb.example config/unicorn.rb
	nano config/unicorn.rb
Dort auf **Zeile 5** die Eigenschaft `app_dir` auf den Pfad der eigenen Gitlab-Installation setzen (bspw. `/home/kanedo/gitlab/`)

###Datenbank
Jetzt kommt die Datenbank dran. Legt vorher eine DB an (bspw über PHPMyAdmin). Die Konfiguration ist dann Denkbar einfach:

	cp config/database.yml.mysql config/database.yml
	nano config/database.yml

Es interessiert euch lediglich der Bereich `production`. 

	#
	# PRODUCTION
	#
	production:
	  adapter: mysql2
	  encoding: utf8
	  reconnect: false
	  database: <user>_datenbankname
	  pool: 5
	  username: <user>
	  password: <password>
	  # host: localhost
	  # socket: /tmp/mysql.sock
	 
###Resque-Konfiguration
	cp config/resque.yml.example config/resque.yml
	nano config/resque.yml
In dieser Datei ersetzt ihr `production: ...` durch:
	
	'unix:/home/<user>/.redis/sock'
	
Dafür war auch der Patch wichtig (unter anderem)
###charlock_holmes installieren
	gem install charlock_holmes --version '0.6.9'
Prüft vorher ob ihr den nicht schon installiert habt mit

	gem list --local | grep charlock

Die Installation kann schon sehr lange dauern!

###Abhängigkeiten installieren
	bundle install --deployment --without development test postgres	

###Hooks konfigurieren
	nano ./lib/hooks/post-receive 
Hier jetzt `redis-cli` durch 

	/home/<euer uberspace name>/.toast/armed/bin/redis-cli -s /home/<euer uberspace name>/.redis/sock  
	
ersetzen. Anschließend 

	cp ./lib/hooks/post-receive ~/.gitolite/hooks/common/post-receive
	
###Gitlab Setup
	bundle exec rake gitlab:setup RAILS_ENV=production

###Setup testen
	bundle exec rake gitlab:check RAILS_ENV=production
	
Da darf kein Fehler auftreten, bis auf das `init script` und `sidekiq`

##Daemontools Konfiguration
Jetzt benötigen wir noch das Daemontools-Skript damit sich gitlab automatisch startet.
Eine Installationsanleitung findet man im [Dokuwiki](http://uberspace.de/dokuwiki/system:daemontools)
Die Skripte sind auf [Github](https://github.com/kanedo/Gitlab-Uberspace/tree/master/services)
Die ladet ihr euch herunter, legt es bspw. nach `~/bin/`. Ausführbar wird es durch
	
<!---
	chmod +x ~/bin/sidekiq
-->

	chmod +x ~/bin/gitlab
In dem Skript ist der Port angegeben, auf den Gitlab lauschen soll, den müsst ihr noch einstellen. Er muss 5-stellig sein und auf eurem Host frei. Wenn die gitlab-Installation in `~/gitlab` liegt reicht das an Änderungen.

Den Service legt ihr nun mit
<!---
	uberspace-setup-service sidekiq ~/bin/sidekiq  
-->

	uberspace-setup-service gitlab ~/bin/gitlab

an. Damit wird nun `redis` und `gitlab` automatisch gestartet. [Zum Thema `sidekiq` habe ich am Schluss noch etwas angemerkt](#ein_paar_anmerkungen)
##Host Konfigurieren
Legt eine `.htaccess`-Datei in dem Ordner, der die Gitlab-Installation öffentlich bereitstellen soll an (in meinem Fall zum Beispiel `/var/www/virtual/kanedo/git.kanedo.net` für die Sub-Domain `git.kanedo.net`) in diese `.htaccess` schreibt ihr nun folgendes, wobei der Port natürlich an euren angepasst werden muss.

	<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteBase /
	RewriteRule ^(.*)$ http://127.0.0.1:<port>/$1 [P]
	</IfModule>

Jetzt mittels `svc -u ~/service/sidekiq` und `svc -u ~/service/gitlab` die Dienste starten, kurz waren und sich bei gitlab einloggen.
Standard-Login ist:

	admin@local.host
	5iveL!fe

##SSH-Key-Verwirrung
Bisher habe ich noch nichts mit den SSH-Keys gemacht. Dafür legt ihr euch einen neuen Benutzer in gitlab an und fügt euren persönlichen SSH-Key ein.
Wenn ihr jetzt versucht euch per SSH zu verbinden, werdet ihr folgende Meldung erhalten:  
![SSH Antwort von Gitolite](http://blog.kanedo.net/files/2013/02/Screen-Shot-2013-02-05-at-11.51.53.png "Gitolite sagt euch wie ihr heißt.")  
Das beheben wir jetzt. (Entweder ihr habt noch eine SSH-Verbindung offen, oder [der Hinweis am Anfang](#falls_etwas_mit_dem_ssh_unterwegs_schiefgeht)) hilft euch weiter.

Wichtig ist jedoch, dass ihr euch einmal per SSH (und ohne Passwort sonder mit Key) anmeldet. Dann sagt euch gitolite nämlich unter welchem Namen es euch kennt. Den braucht ihr jetzt.
Ihr seid wieder per SSH verbunden und im Home Verzeichniss. Dann folgendes ausführen:

	echo "<hier der gitolite user name>" >> .gitolite.shell-users
dann bearbeiten wir jetzt die `.gitolite.rc` folgendermaßen:

	SHELL_USERS_LIST        =>  "$ENV{HOME}/.gitolite.shell-users",
Fügt ihr an beliebige Stelle ein, dann sucht ihr nach dem Abschnitt `INPUT` und entfernt `#` vor ` 'Shell::input', `. Außerdem sucht ihr den Abschnitt `POST_COMPILE` und fügt dort **nach** `'post-compile/ssh-authkeys', ` die Zeile

	'post-compile/ssh-authkeys-shell-users',

ein. Dann speichern und zum Schluss müssen wir gitolite noch erzählen, dass sich etwas geändert hat.
Das geschieht mit:

	gitolite compile; gitolite trigger POST_COMPILE
	
Fertig. Euer Gitlab sollte laufen, ihr dürft pushen und SSH-Zugang habt ihr auch noch.

##Fehlermeldungen und was man dagegen machen kann
###`bundle exec rake gitlab:setup RAILS_ENV=production` 
####mit Fehler `rake aborted! No route to host - connect(2)`
Scheinbar wurde der Patch nicht angewandt. Wichtig ist, dass in der Datei `config/initializers/4_sidekiq.rb` an zwei Stellen das `redis://` __komplett__ entfernt wird.
####mit Fehler `No such file or directory – connect(2)`
Vorher Redis starten mit `redis-server $HOME/.redis/conf` 
##Ein paar Anmerkungen
Leider habe ich es nicht geschafft, den Sidekiq-Prozess als Daemon laufen zu lassen (Auch wenn es da einen Service im Repository zu gibt). Der geht immer in den Hintergrund und wird dadurch von Daemontools neu gestartet und läuft dan Amok.
Deswegen müsst ihr im Moment sidekiq noch händisch starten mittels 

	exec bundle exec rake sidekiq:start RAILS_ENV=production

im gitlab Ordner.  
Vielleicht findet sich jemand, der das beheben kann. Am liebsten wäre es mir ja, wenn ich sidekiq direkt im gitlab-service starten könnte.  
  
Ich hoffe ihr könnt etwas damit Anfangen. Bei mir läuft es wunderprächtig.
