# Balast entfernen
Um alles Uberspace-konform zu installieren, m�ssen wir zun�chst etwas "aufr�umen".

## Redis l�schen
Da `redis` nun direkt von Uberspace bereitgestellt wird, bietet es sich an, diesen zu installieren. Wer seine Redis-Installation aber nicht neu installieren m�chte, muss dies nicht zwangsl�ufig tun. Eine "saubere" Installation wird jedoch empfohlen.

Hinweis: Wenn ihr eure Redis-Installation behalten m�chtet, m�sst ihr fortan selbst daf�r sorgen, dass es �ber die deamontools gestartet wird.

### Laufende Instanzen beenden
Zun�chst einmal m�ssen wir die aktuell laufende `redis`-Instanz beendet werden.

Wer nicht wei�, wie das funktioniert:

    pkill redis-server

Pr�ft nun, ob noch eine Instanz l�uft:

    ps aux | grep <uberspace-name>
   
Falls noch eine Instanz l�uft:

    kill <process-id>
    
### Deinstallation
Zur Deinstallation nutzen wir `toast` und l�schen anschlie�end das Konfigurationsverzeichnis:

    toast disarm redis
    toast remove redis
    rm -rf ~/.redis

## RVM entfernen
    rvm implode
    gem uninstall rvm

(Quelle: http://stackoverflow.com/a/3558763)

Falls das `.rvm`-Verzeichnis nicht gel�scht wurde, tut dies manuell:

    rm -rf ~/.rvm

# Vorbereitungen (s. anderes Tutorial)

# Gitolite �berspringen
Habt ihr ja bereits installiert. Lassen wir so, wie es ist :)

# Gitlab
Sicherheitshalber sollten wir `sidekiq` und `gitlab` stoppen:

    svc -d ~/service/gitlab

Sidekiq stoppen wir h�ndisch, indem wir uns dessen PID suchen und den Prozess mit `kill` stoppen (siehe ggf. [hier](#laufende-instanzen-beenden)

## GitLab aktualisieren
    cd ~/gitlab
    git fetch
    git checkout 4-2-stable

## Patch anwenden 

    curl https://raw.github.com/kanedo/Gitlab-Uberspace/master/uberspace.patch -o uberspace.patch
    git apply --check uberspace.patch

Bitte versichert euch, dass der Patch auch wirklich angewendet wurde. 
    
## Konfiguration
Die Konfiguration kann �bersprungen werden. Ihr habt ja schon alles konfiguriert ;-)

## Abh�ngigkeiten installieren
    bundle install --without development test postgres --deployment

## Migration statt Installation
    bundle exec rake db:migrate RAILS_ENV=production

Anschlie�end ggf. noch sidekiq starten und fertig.