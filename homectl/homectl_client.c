#ifndef UNICODE
#define UNICODE
#endif
#ifndef _UNICODE
#define _UNICODE
#endif
#include <Winsock2.h>
#include <windows.h>
#include <ws2tcpip.h>
#include <windns.h>
#include <iphlpapi.h>
#include <stdlib.h>
#include <stdio.h>
#include <conio.h>
#include <string.h>
#include <powrprof.h>

#pragma comment(lib, "Kernel32.lib")
#pragma comment(lib, "User32.lib")
#pragma comment(lib, "Ws2_32.lib")
#pragma comment(lib, "Dnsapi.lib")
#pragma comment(lib, "Iphlpapi.lib")
#pragma comment(lib, "PowrProf.lib")

#pragma comment(linker, "/SUBSYSTEM:WINDOWS")

#define USAGE_TEXT L"Usage: homectl_client.exe homepi.local 15520\n"
#define RECONNECT_TIMEOUT 60000
#define MAX_RECONNECTS 10
#define TIME_MAX_DELAY 2
#define BUFSIZE 1024
#define DEBUG
#undef DEBUG

typedef struct
{
	OVERLAPPED Overlapped;
	WSABUF buf;
	CHAR buffer[BUFSIZ];
} PER_IO_OPERATION_DATA, * LPPER_IO_OPERATION_DATA;

// https://stackoverflow.com/questions/20370920/convert-current-time-from-windows-to-unix-timestamp-in-c-or-c
ULONGLONG GetSystemTimeAsUnixTime()
{
	//Get the number of seconds since January 1, 1970 12:00am UTC
	//Code released into public domain; no attribution required.

	const ULONGLONG UNIX_TIME_START = 0x019DB1DED53E8000; //January 1, 1970 (start of Unix epoch) in "ticks"
	const ULONGLONG TICKS_PER_SECOND = 10000000; //a tick is 100ns

	FILETIME ft;
	GetSystemTimeAsFileTime(&ft); //returns ticks in UTC

	//Copy the low and high parts of FILETIME into a LARGE_INTEGER
	//This is so we can access the full 64-bits as an Int64 without causing an alignment fault
	LARGE_INTEGER li;
	li.LowPart = ft.dwLowDateTime;
	li.HighPart = ft.dwHighDateTime;

	//Convert ticks since 1/1/1970 into seconds
	return (li.QuadPart - UNIX_TIME_START) / TICKS_PER_SECOND;
}

void ___exit(int code)
{
#ifdef DEBUG
	_getch();
#endif
	exit(code);
}

DWORD WINAPI recvThread(LPVOID fd)
{
	HANDLE completion_port = (HANDLE)fd;
	SOCKET sockfd;
	DWORD recv_bytes;
	LPPER_IO_OPERATION_DATA data;
	CHAR buffer[BUFSIZE];
	CHAR* pos = buffer, *endpos = NULL, *data_pos = NULL;
	SIZE_T len = 0, i;
	DWORD flags = 0;
	INT steps, err, time_offset = 0;
	LPARAM lParam;
	WPARAM wParam;
	BOOL ignore = FALSE;
	LONG recv_timestamp, epoch_timestamp;

	ZeroMemory(buffer, BUFSIZE);

	while (TRUE)
	{
		if (GetQueuedCompletionStatus(
			completion_port,
			&recv_bytes,
			(SOCKET*)&sockfd,
			(LPOVERLAPPED*)&data,
			INFINITE) == 0
			)
		{
			printf("GetQueuedCompletionStatus %d\n", GetLastError());
			return 1;
		}

		if (recv_bytes == 0)
		{
			return 1;
		}

		epoch_timestamp = GetSystemTimeAsUnixTime();
		memcpy(pos, data->buffer, recv_bytes);
		len = strlen(buffer);
		endpos = strchr(buffer, '\n');
		data_pos = strchr(buffer, ' ') + 1;
		if (endpos != NULL)
		{
			*(data_pos - 1) = 0;
			endpos[0] = 0;

			recv_timestamp = strtol(buffer, NULL, 10);
			//printf("%d %ld %ld\n", abs(recv_timestamp - epoch_timestamp) - TIME_OFFSET, recv_timestamp, epoch_timestamp);
			if (data_pos[0] == 's' && data_pos[1] == 'n' && data_pos[2] == 'k')
			{
				time_offset = abs(recv_timestamp - epoch_timestamp);
				//printf("%d\n", time_offset);
			}
			else if (abs(recv_timestamp - epoch_timestamp) - time_offset < TIME_MAX_DELAY)
			{

				if (data_pos[0] == 'w' && data_pos[1] == 'h' && data_pos[2] == 'l')
				{
					steps = atoi(data_pos + 4);
					if (steps < 0)
					{
						lParam = APPCOMMAND_VOLUME_DOWN;
						steps = -steps;
					}
					else
					{
						lParam = APPCOMMAND_VOLUME_UP;
					}
					wParam = (WPARAM)FindWindow(L"ProgMan", NULL);
					for (i = 0; i < steps; ++i)
					{
						SendMessage(
							(HWND)wParam,
							WM_APPCOMMAND,
							wParam,
							lParam * 65536
						);
					}
				}
				else if (data_pos[0] == 'b' && data_pos[1] == 't' && data_pos[2] == 'n' && data_pos[4] == '0')
				{
					keybd_event(VK_MEDIA_PLAY_PAUSE, 0, 0, 0);
					keybd_event(VK_MEDIA_PLAY_PAUSE, 0, KEYEVENTF_KEYUP, 0);
				}
				else if (data_pos[0] == 'b' && data_pos[1] == 't' && data_pos[2] == 'n' && data_pos[4] == '1')
				{
					lParam = APPCOMMAND_VOLUME_MUTE;
					wParam = (WPARAM)FindWindow(L"ProgMan", NULL);
					SendMessage(
						(HWND)wParam,
						WM_APPCOMMAND,
						wParam,
						lParam * 65536
					);
				}
				else if (data_pos[0] == 'b' && data_pos[1] == 't' && data_pos[2] == 'n' && data_pos[4] == '2')
				{
					if (!ignore)
					{
						SetSuspendState(FALSE, FALSE, FALSE);
						ignore = TRUE;
					}
					else
					{
						ignore = FALSE;
					}
				}
			}
			for (i = 0; i < len - (endpos + 1 - buffer); ++i)
			{
				buffer[i] = buffer[i + (endpos + 1 - buffer)];
			}
			pos = buffer;
		}
		else
		{
			pos = pos + recv_bytes;
		}

		if (WSARecv(
			sockfd,
			&(data->buf),
			1,
			&recv_bytes,
			&flags,
			&(data->Overlapped),
			NULL) == SOCKET_ERROR
			)
		{
			err = WSAGetLastError();
			if (err == WSAECONNRESET)
			{
				return 1;
			}
			if (err != ERROR_IO_PENDING)
			{
				printf("WSARecv2 %d\n", err);
				___exit(EXIT_FAILURE);
			}
		}
		
	}
	return 0;
}

int WINAPI wWinMain(
	HINSTANCE hInstance, 
	HINSTANCE hPrevInstance, 
	PWSTR pCmdLine, 
	int nCmdShow
)
{
	CHAR buffer[BUFSIZE];
	FILE* conout;
	DNS_STATUS status;
	PDNS_RECORD pDnsRecord;
	IN_ADDR ipaddr;
	WSADATA wsaData;
	SOCKET sockfd = INVALID_SOCKET;
	struct addrinfo hints;
	struct sockaddr_in serv_addr;
	HANDLE completion_port = NULL, thread = NULL, event = NULL;
	LPPER_IO_OPERATION_DATA data;
	DWORD flags = 0, recv_bytes = 0, exit_code = 1, knt = 0;
	INT err;

#ifdef DEBUG
	AllocConsole();
	freopen_s(
		&conout, 
		"CONOUT$", 
		"w", 
		stdout
	);
#endif

	if (__argc < 3)
	{
#ifdef DEBUG
		printf(USAGE_TEXT);
#else
		MessageBox(0, USAGE_TEXT, L"homepi", MB_OK | MB_ICONSTOP);
#endif
		___exit(EXIT_FAILURE);
	}

	if ((completion_port = CreateIoCompletionPort(
		INVALID_HANDLE_VALUE,
		NULL,
		0,
		0)) == NULL)
	{
		printf("Init CreateIoCompletionPort %d\n", GetLastError());
		___exit(EXIT_FAILURE);
	}
	if (WSAStartup(MAKEWORD(2, 2), &wsaData) != 0)
	{
		printf("WSAStartup\n");
		___exit(EXIT_FAILURE);
	}

	while (exit_code)
	{
		ZeroMemory(buffer, BUFSIZE);

		if (DnsQuery(
			__wargv[1],
			DNS_TYPE_A,
			DNS_QUERY_BYPASS_CACHE,
			NULL,
			&pDnsRecord,
			NULL) != 0
			)
		{
			printf("DnsQuery\n");
			if (NotifyAddrChange(NULL, NULL) == NO_ERROR) 
			{
				Sleep(5000);
				continue;
			}
			else
			{
				printf("NotifyAddrChange\n");
				exit(EXIT_FAILURE);
			}
		}
		ipaddr.S_un.S_addr = (pDnsRecord->Data.A.IpAddress);
		inet_ntop(AF_INET, &ipaddr, buffer, BUFSIZE);
		DnsRecordListFree(pDnsRecord, freetype);

		ZeroMemory(&serv_addr, sizeof(serv_addr));
		serv_addr.sin_family = AF_INET;
		serv_addr.sin_addr.S_un.S_addr = (pDnsRecord->Data.A.IpAddress);
		serv_addr.sin_port = htons(_wtoi(__wargv[2]));

		ZeroMemory(&hints, sizeof(hints));
		hints.ai_family = AF_INET;
		hints.ai_socktype = SOCK_STREAM;
		hints.ai_protocol = IPPROTO_TCP;
		hints.ai_addr = (struct sockaddr*) & serv_addr;
		hints.ai_addrlen = sizeof(struct sockaddr);

		sockfd = WSASocket(
			hints.ai_family,
			hints.ai_socktype,
			hints.ai_protocol,
			NULL,
			0,
			WSA_FLAG_OVERLAPPED
		);
		if (sockfd == INVALID_SOCKET)
		{
			printf("WSASocket %ld\n", WSAGetLastError());
			___exit(EXIT_FAILURE);
		}

		if (connect(sockfd, hints.ai_addr, hints.ai_addrlen) == SOCKET_ERROR)
		{
			if (knt < MAX_RECONNECTS)
			{
				knt++;
				closesocket(sockfd);
				Sleep(RECONNECT_TIMEOUT);
				continue;
			}
			printf("connect %d\n", WSAGetLastError());
			___exit(EXIT_FAILURE);
		}
		knt = 0;

		if ((thread = CreateThread(
			NULL,
			0,
			recvThread,
			completion_port,
			0,
			NULL)
			) == NULL)
		{
			printf("CreateThread %d\n", GetLastError());
			___exit(EXIT_FAILURE);
		}

		if (CreateIoCompletionPort(
			(HANDLE)sockfd,
			completion_port,
			sockfd,
			0) == NULL
			)
		{
			printf("CreateIoCompletionPort %d\n", GetLastError());
			___exit(EXIT_FAILURE);
		}

		if ((data = malloc(sizeof(PER_IO_OPERATION_DATA))) == NULL)
		{
			printf("malloc %d\n", GetLastError());
			___exit(EXIT_FAILURE);
		}

		ZeroMemory(&data->Overlapped, sizeof(OVERLAPPED));
		data->buf.len = BUFSIZE;
		data->buf.buf = data->buffer;

		if (WSARecv(
			sockfd,
			&(data->buf),
			1,
			&recv_bytes,
			&flags,
			&(data->Overlapped),
			NULL) == SOCKET_ERROR
			)
		{
			err = WSAGetLastError();
			if (err == WSAECONNRESET)
			{
				return 1;
			}
			if (err != ERROR_IO_PENDING)
			{
				printf("WSARecv2 %d\n", err);
				___exit(EXIT_FAILURE);
			}
		}

		WaitForSingleObject(
			thread,
			INFINITE
		);
		GetExitCodeThread(
			thread,
			&exit_code
		);
		free(data);
		if (closesocket(sockfd) == SOCKET_ERROR)
		{
			printf("closesocket() %d\n", WSAGetLastError());
			___exit(EXIT_FAILURE);
		}
		if (CloseHandle(thread) == FALSE)
		{
			printf("CloseHandle %d\n", GetLastError());
			___exit(EXIT_FAILURE);
		}
	}

	WSACleanup();

	return 0;
}