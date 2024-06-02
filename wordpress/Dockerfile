from wordpress:6.5.3-php8.3-apache

RUN set -ex; \
	\
	apt-get update && \
	apt-get install -y --no-install-recommends \
		libx11-dev \
		build-essential \
		cmake \
		wget \
		unzip \
	; \
	cd /tmp; \
	wget https://github.com/davisking/dlib/archive/refs/tags/v19.24.4.zip && unzip v19.24.4.zip \
		&& cd dlib-19.24.4/dlib && mkdir build && cmake -DBUILD_SHARED_LIBS=ON .. && make && make install; \
	wget https://github.com/goodspb/pdlib/archive/refs/tags/v1.1.0.zip && unzip v1.1.0.zip \
		&& cd pdlib-1.1.0 && phpize && ./configure && make && make install \
		&& echo -e '[pdlib]\nextension="pdlib.so"' >> /usr/local/etc/php/conf.d/docker-php-ext-pdlib.ini
