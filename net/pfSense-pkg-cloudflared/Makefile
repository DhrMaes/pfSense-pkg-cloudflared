# $FreeBSD$

PORTNAME=	pfSense-pkg-cloudflared
PORTVERSION?=	1.0.0
CATEGORIES=	net
MASTER_SITES=	# none
DISTFILES=	# none

MAINTAINER=	DhrMaes
COMMENT=	pfSense package for Cloudflare Tunnel (cloudflared)

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/bin/cloudflared:net/cloudflared

NO_BUILD=	yes
NO_MTREE=	yes
NO_ARCH=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

DATADIR=	${PREFIX}/share/${PORTNAME}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/rc.d
	${MKDIR} ${STAGEDIR}${PREFIX}/share/${PORTNAME}

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/cloudflared.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/cloudflared.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/cloudflared_tunnels.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/cloudflared_status.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/etc/rc.d/cloudflared.sh \
		${STAGEDIR}${PREFIX}/etc/rc.d/cloudflared
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/${PORTNAME}/info.xml \
		${STAGEDIR}${PREFIX}/share/${PORTNAME}/info.xml

.include <bsd.port.mk>
