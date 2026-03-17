# Makefile for owrt_client
CC ?= gcc
CFLAGS ?= -Wall -Wextra -std=c99 -D_GNU_SOURCE
LDFLAGS ?= 
LIBS ?= -lcurl -ljson-c -lssl -lcrypto

SRCDIR = .
INCDIR = ../include
OBJDIR = obj

# 包含所有源文件，包括新的crypto.c
SOURCES = main.c network.c config.c command.c logging.c crypto.c
OBJECTS = $(SOURCES:%.c=$(OBJDIR)/%.o)
TARGET = owrt_client

.PHONY: all clean

all: $(TARGET)

$(TARGET): $(OBJECTS)
	$(CC) $(LDFLAGS) $(OBJECTS) -o $@ $(LIBS)

$(OBJDIR)/%.o: $(SRCDIR)/%.c
	@mkdir -p $(OBJDIR)
	$(CC) $(CFLAGS) -I$(INCDIR) -c $< -o $@

clean:
	rm -rf $(OBJDIR) $(TARGET)
