# Balast entfernen
Um alles Uberspace-konform zu installieren, müssen wir zunächst etwas "aufräumen".

## Redis löschen
Da `redis` nun direkt von Uberspace bereitgestellt wird, bietet es sich an, diesen zu installieren. Wer seine Redis-Installation aber nicht neu installieren möchte, muss dies nicht zwangsläufig tun. Eine "saubere" Installation wird jedoch empfohlen.

Hinweis: Wenn ihr eure Redis-Installation behalten möchtet, müsst ihr fortan selbst dafür sorgen, dass es über die deamontools gestartet wird.

### Laufende Instanzen beenden
Zunächst einmal müssen wir die aktuell laufende `redis`-Instanz beendet werden.

Wer nicht weiß, wie das funktioniert:

    pkill redis-server

Prüft nun, ob noch eine Instanz läuft:

    ps aux | grep <uberspace-name>
   
Falls noch eine Instanz läuft:

    kill <process-id>
    
### Deinstallation
Zur Deinstallation nutzen wir `toast` und löschen anschließend das Konfigurationsverzeichnis:

    toast disarm redis
    toast remove redis
    rm -rf ~/.redis

## RVM entfernen
    rvm implode
    gem uninstall rvm

(Quelle: http://stackoverflow.com/a/3558763)

Falls das `.rvm`-Verzeichnis nicht gelöscht wurde, tut dies manuell:

    rm -rf ~/.rvm

# Vorbereitungen (s. anderes Tutorial)

# Gitolite überspringen
Habt ihr ja bereits installiert. Lassen wir so, wie es ist :)

# Gitlab
Sicherheitshalber sollten wir `sidekiq` und `gitlab` stoppen:

    svc -d ~/service/gitlab

Sidekiq stoppen wir händisch, indem wir uns dessen PID suchen und den Prozess mit `kill` stoppen (siehe ggf. [hier](#laufende-instanzen-beenden)

## GitLab aktualisieren
    cd ~/gitlab
    git fetch
    git checkout 4-2-stable

## Patch anwenden 

    curl https://raw.github.com/kanedo/Gitlab-Uberspace/master/uberspace.patch -o uberspace.patch
    git apply --check uberspace.patch

Bitte versichert euch, dass der Patch auch wirklich angewendet wurde. 
    
## Konfiguration
Die Konfiguration kann übersprungen werden. Ihr habt ja schon alles konfiguriert ;-)

## Abhängigkeiten installieren
    bundle install --without development test postgres --deployment

## Migration statt Installation
    bundle exec rake db:migrate RAILS_ENV=production

Anschließend ggf. noch sidekiq starten und fertig.