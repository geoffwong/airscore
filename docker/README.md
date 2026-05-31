
Run it something like this:

docker run -p 8080:80 --env=PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin --env=DEBIAN_FRONTEND=noninteractive --network=bridge --workdir=/var/www --restart=no ubuntu-image

