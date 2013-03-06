# Wichtige Information
Da die Installation von GitLab nicht unproblematisch ist, solltet ihr das Tutorial zum einen sehr aufmerksam lesen und bei jedem Schritt immer einen Blick auf die Sektionen "Fehlermeldungen" und "Bekannte Bugs" werfen.

# Vorbereitung

## Redis installieren
Schaut zun�chst einmal, ob ihr die `deamontools` auf eurem Uberspace eingerichtet habt. Falls ihr noch kein `~/service`-Verzeichnis habt, legt es euch mit folgendem Befehl an:

    uberspace-setup-svscan

Nun kann `redis` installiert werden:

    uberspace-setup-redis

Redis sollte nun installiert sein. Es l�uft nun als zentraler Service auf eurem Uberspace.

## Ruby auf Version 1.9.3 setzen
GitLab erfordert Ruby 1.9.3, also m�ssen wir dies auf eurem Uberspace aktivieren:

    cat <<'__EOF__' >> ~/.bash_profile
    export PATH=/package/host/localhost/ruby-1.9.3/bin:$PATH
    export PATH=$HOME/.gem/ruby/1.9.1/bin:$PATH
    __EOF__

Nun m�ssen wir die ge�nderte Konfiguration noch einlesen:

    . ~/.bash_profile

## Python2 auf Python2.7 setzen
    cat <<'__EOF__' >> ~/.bash_profile
    alias python2=python2.7
    __EOF__

## RubyGems vorbereiten
Da wir eine global installierte Ruby-Installation verwenden, m�ssen wir RubyGems mitteilen, dass es Gems nur lokal (also f�r uns) installieren soll. Anderenfalls wird versucht, die Gems global zu installieren, was allerdings mangels root-Rechten scheitert. Ein Aufruf von

    echo "gem: --user-install --no-rdoc --no-ri" > ~/.gemrc

gen�gt, um absofort �ber `gem install <package>` eigene Gems installieren zu k�nnen.

## Bundler installieren
    gem install bundler

# Gitolite
Als n�chstes muss die Version 3.2 von gitolite installiert werden. Wer schon gitolite installiert hat, macht am besten ein Backup und deinstalliert die alte Version mittels der[ Anleitung im Dokuwiki](https://uberspace.de/dokuwiki/cool:gitolite#deinstallation) Die Repos d�rfen liegen bleiben!  
### Version 3.2 aus dem Repo clonen und installieren
    git clone -b gl-v320 https://github.com/gitlabhq/gitolite.git ./gitolite  
    gitolite/install -ln ~/bin  
	 
###SSH-Key f�r den Admin generieren
    ssh-keygen -f admin
    
Wichtig: Der Key darf nicht mit einer Passphrase versehen werden!
    
###Gitolite instalieren
	gitolite setup -pk admin.pub  
	mv admin .ssh/id_rsa  
	mv admin.pub .ssh/id_rsa.pub
Jetzt unbedingt testen, ob man Admin von gitolite ist (sonst nicht weitermachen!):  
	
	git clone <user>@<user>.<host>.uberspace.de:gitolite-admin.git
	
Wenn das geklappt hat, den Admin wieder l�schen:

	rm -rf gitolite-admin/

###Gitolite Rechte
	chmod 750 ~/.gitolite  
	chmod -R ug+rwXs,o-rwx ~/repositories/


# Gitlab
Jetzt gitlab wirklich installieren. Leider muss man ein paar Dinge nicht nur in den Konfigurationsdateien anpassen, deswegen habe ich einen Patch auf Github gestellt. In diesem Repository liegen auch die Skripte f�r die daemontools-services

    git clone https://github.com/gitlabhq/gitlabhq.git gitlab 
    cd gitlab
    git checkout 4-2-stable

Jetzt den Patch anwenden:

    curl https://raw.github.com/kanedo/Gitlab-Uberspace/master/uberspace.patch -o uberspace.patch
    git apply --check uberspace.patch
    
Bitte versichert euch, dass der Patch auch wirklich angewendet wurde.

## Konfiguration
    cp config/gitlab.yml.example config/gitlab.yml
    nano config/gitlab.yml

In dieser Datei m�ssen verschiedene Parameter angepasst werden. Die Liste ist in folgendem Format: Zeile:Key,Beschreibung

* 18 : **host**, Der Domainname unter dem Gitlab laufen soll
* 26 : **user**, Dein Uberspace Account
* 99 : **path**, Pfad zu den gitlab sattelites (noch keine Idee was das ist, ich habe nur den Pfad so angepasst, dass er nun mein home-Verzeichniss als Stamm hat)
* 108 : **admin_uri**, SSH-Pfad zum auschecken des Gitolite-Admin-Repositories (Siehe Gitolite installieren)
* 110 : **repos_path**, Pfad zu den Repositories (meist /home/<uberspace-name>/repositories)
* 111 : **hooks_path**, Pfad zu den Gitolite-Hooks (Hier den Home-Pfad durch euren ersetzen)
* 112 : **admin_key**, Name des Admin-Keys, in unserem Fall admin
* 115 : **ssh_user**, Uberspace-Name
* 117: **ssh_host**, Hier den qualifizierten Hostnamen eintragen f�r sp�teres pushen via SSH

## Unicorn
    cp config/unicorn.rb.example config/unicorn.rb
    nano config/unicorn.rb

Dort auf Zeile 5 die Eigenschaft `app_dir` auf den Pfad der eigenen Gitlab-Installation setzen (in unserem Fall ist dies /home/<uberspace-name>/gitlab).

## Datenbank
Jetzt kommt die Datenbank dran. Legt vorher eine DB an (bspw. �ber PHPMyAdmin). Die Konfiguration ist dann Denkbar einfach:

    cp config/database.yml.mysql config/database.yml
    nano config/database.yml

Es interessiert euch lediglich der Bereich production.
    #
    # PRODUCTION #
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

## Resque-Konfiguration
    cp config/resque.yml.example config/resque.yml
    nano config/resque.yml

In dieser Datei ersetzt ihr `production: ...` durch:

    production: 'unix:/home/<euer uberspace name>/.redis/sock'

Daf�r war auch der Patch wichtig (unter anderem).

## charlock_holmes installieren
    gem install charlock_holmes --version '0.6.9'

Pr�ft vorher ob ihr den nicht schon installiert habt mit

    gem list --local | grep charlock

Die Installation kann schon sehr lange dauern!

## Abh�ngigkeiten installieren
    bundle install --deployment --without development test postgres --path ~/.gem

Anmerkung: Das `--path ~/.gem` sorgt daf�r, dass Gems nicht global, sondern lokal installiert werden. (vgl. (https://uberspace.de/dokuwiki/development:ruby#bundler)

## Hooks konfigurieren
nano ./lib/hooks/post-receive

Hier jetzt `redis-cli` durch

    redis-cli -s /home/<euer uberspace name>/.redis/sock

ersetzen. Anschlie�end

    cp ./lib/hooks/post-receive ~/.gitolite/hooks/common/post-receive

ausf�hren.

## Gitlab Setup
    bundle exec rake gitlab:setup RAILS_ENV=production

## Setup testen
    bundle exec rake gitlab:check RAILS_ENV=production

Da darf kein Fehler auftreten, bis auf das `init script` und `sidekiq`. Sollte es dennoch Fehler geben, lest euch diese genau durch. Meistens wird direkt eine L�sung des Problems vorgeschlagen.

Sollte es eine Fehlermeldung bzgl. einer `.profile`-Datei geben, hilft es, diese einfach anzulegen:

    touch ~/.profile

# Daemontools Konfiguration
Jetzt ben�tigen wir noch das Daemontools-Skript damit sich gitlab automatisch startet.

Eine Installationsanleitung findet man im [Dokuwiki](http://uberspace.de/dokuwiki/system:daemontools)
Die Skripte sind auf [Github](https://github.com/kanedo/Gitlab-Uberspace/tree/master/services)

Die ladet ihr euch herunter, legt es bspw. nach `~/bin/`. Ausf�hrbar wird es durch
    
    chmod +x ~/bin/gitlab

In dem Skript ist der Port angegeben, auf den Gitlab lauschen soll, den m�sst ihr noch einstellen. Er muss 5-stellig sein und auf eurem Host frei. Wenn die gitlab-Installation in `~/gitlab` liegt reicht das an �nderungen.

Den Service legt ihr nun mit
    
    uberspace-setup-service gitlab ~/bin/gitlab

an. Damit wird nun `gitlab` automatisch gestartet. Zum Thema `sidekiq` habe ich am Schluss noch etwas angemerkt.

# Host Konfiguration
Legt eine `.htaccess`-Datei in dem Ordner, der die Gitlab-Installation �ffentlich bereitstellen soll an (in meinem Fall zum Beispiel `/var/www/virtual/kanedo/git.kanedo.net` f�r die Sub-Domain `git.kanedo.net`) in diese `.htaccess` schreibt ihr nun folgendes, wobei der Port nat�rlich an euren angepasst werden muss.

	<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteBase /
	RewriteRule ^(.*)$ http://127.0.0.1:<port>/$1 [P]
	</IfModule>

Jetzt mittels `svc -u ~/service/gitlab` GitLab starten. Mit Hilfe von

    exec bundle exec rake sidekiq:start RAILS_ENV=production

startet ihr noch sidekiq. Nicht wundern, das Skript verbannt sich in den Hintergrund - bei Putty beendet sich daher das Konsolenfenster. Nun k�nnt ihr euch in eurem GitLab anmelden. Der Standard-Login ist:

	admin@local.host
	5iveL!fe

# SSH-Key-Verwirrung
<AUS ALTEM TUTORIAL EINF�GEN>

##Fehlermeldungen und was man dagegen machen kann
    bundle exec rake gitlab:setup RAILS_ENV=production
mit Fehler
    rake aborted! No route to host - connect(2)

Scheinbar wurde der Patch nicht angewandt. Wichtig ist, dass in der Datei `config/initializers/4_sidekiq.rb` an zwei Stellen das `redis://` __komplett__ entfernt wird.

## Bekannte Bugs
### Attachments: Error 404
Mit unserer Apache-Konfiguration ist es nicht m�glich, den `/uploads`-Ordner zu mappen. Derzeit ist kein Workaround bekannt, da wir beim Apache nicht viel Spielraum f�r eigene Konfigurationen haben. Falls es jemand dennoch hinbekommen hat, bitte melden!

### Projekte klonen funktioniert nicht
Benutzer ohne SSH-Schl�ssel k�nnen keine Projekte erstellen. Zwar macht GitLab den Eindruck, dass ein Projekt angelegt wurde, schaut man aber ins Dateisystem werden die Projekte gar nicht erst angelegt. F�gt man dem Benutzer einen SSH-Schl�ssel hinzu, klappt es wunderbar.

### Zugriff mit SSH-Key funktioniert nicht
Nutzt man in GitLab den gleichen SSH-Key wie in Uberspace, so k�nnte dies die Ursache f�r das Problem sein. Entfernt den Schl�ssel aus GitLab und erstellt einen neuen Schl�ssen speziell f�r GitLab. Man hat zwar dann zwei Schl�ssel, aber das hat das Problem bei mir gel�st.

Wenn das Problem immer noch besteht, ist eure Konfiguration defekt.

# Ein paar Anmerkungen