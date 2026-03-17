# OpenWrt Makefile for Remote Client

include $(TOPDIR)/rules.mk

PKG_NAME:=owrt-remote-client
PKG_VERSION:=1.0
PKG_RELEASE:=1

PKG_BUILD_DIR:=$(BUILD_DIR)/$(PKG_NAME)
PKG_CONFIG_DEPENDS:=

include $(INCLUDE_DIR)/package.mk

# 确保 OpenSSL 可用
ifndef CONFIG_PACKAGE_libopenssl
$(warning libopenssl not selected, crypto features may not work)
endif

define Package/owrt-remote-client
  SECTION:=utils
  CATEGORY:=Utilities
  TITLE:=OpenWrt Remote Management Client with AES Encryption
  DEPENDS:=+libcurl +libjson-c +libopenssl
  MAINTAINER:=Misakku
  DEFAULT:=n
endef

define Package/owrt-remote-client/description
  A secure remote management client for OpenWrt routers with AES encryption support.
  Enables remote command execution and monitoring through encrypted HTTP API.
  Features full-chain AES-256-CBC encryption for secure communication.
endef

define Build/Prepare
	mkdir -p $(PKG_BUILD_DIR)
	$(CP) ./src/* $(PKG_BUILD_DIR)/
	$(CP) ./include $(PKG_BUILD_DIR)/
endef

define Build/Compile
	$(MAKE) -C $(PKG_BUILD_DIR) \
		$(TARGET_CONFIGURE_OPTS) \
		CFLAGS="$(TARGET_CFLAGS) -I$(PKG_BUILD_DIR)/include -I$(STAGING_DIR)/usr/include" \
		LDFLAGS="$(TARGET_LDFLAGS) -L$(STAGING_DIR)/usr/lib -lcurl -ljson-c -lssl -lcrypto" \
		CC="$(TARGET_CC)" \
		owrt_client
endef

define Package/owrt-remote-client/install
	$(INSTALL_DIR) $(1)/usr/bin
	$(INSTALL_BIN) $(PKG_BUILD_DIR)/owrt_client $(1)/usr/bin/owrt_client
	$(INSTALL_BIN) ./files/start_client.sh $(1)/usr/bin/owrt_client_start
endef

define Package/owrt-remote-client/postinst
#!/bin/sh
if [ -z "$${IPKG_INSTROOT}" ]; then
	echo "Installing OpenWrt Remote Client with AES Encryption..."
	
	# 初始化UCI配置
	uci set owrt_client.main=section 2>/dev/null
	uci set owrt_client.main.server_url="url"
	uci set owrt_client.main.use_encryption="1"
	uci set owrt_client.main.heartbeat_interval="60"
	uci set owrt_client.main.max_retries="3"
	uci commit owrt_client 2>/dev/null
	
	# Configure and start immediately
	/usr/bin/owrt_client_start
	
	# Setup auto-start on boot via rc.local
	if [ -f "/etc/rc.local" ]; then
		# Remove any existing entries
		sed -i '/owrt_client/d' /etc/rc.local
		# Add before 'exit 0'
		sed -i '/^exit 0/i\/usr/bin/owrt_client_start &' /etc/rc.local
	else
		# Create rc.local if it doesn't exist
		echo '#!/bin/sh' > /etc/rc.local
		echo '/usr/bin/owrt_client_start &' >> /etc/rc.local
		echo 'exit 0' >> /etc/rc.local
		chmod +x /etc/rc.local
	fi
	
	echo ""
	echo "✓ Client configured and started!"
	echo "✓ Auto-start on boot configured!"
	echo "✓ AES encryption enabled!"
	echo ""
	echo "Available commands:"
	echo "  owrt_client_start             - Start/restart client"
	echo ""
	echo "Security features:"
	echo "  ✅ AES-256-CBC encryption enabled"
	echo "  ✅ HMAC-SHA256 integrity verification"
	echo "  ✅ Automatic key exchange with server"
	echo "  ✅ No User-Agent headers (stealth mode)"
	echo "  ✅ Encrypted command transmission"
	echo "  ✅ Full-chain encryption protection"
fi
endef

define Package/owrt-remote-client/prerm
#!/bin/sh
if [ -z "$${IPKG_INSTROOT}" ]; then
	# Stop the client process
	killall owrt_client 2>/dev/null
	
	# Remove auto-start from rc.local
	if [ -f "/etc/rc.local" ]; then
		sed -i '/owrt_client/d' /etc/rc.local
	fi
	
	echo "OpenWrt Remote Client stopped and auto-start removed"
fi
endef

$(eval $(call BuildPackage,owrt-remote-client))
