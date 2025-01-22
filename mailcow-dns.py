# Check for required packages first
def check_dependencies():
    required_packages = {
        'requests': 'requests',
        'dns': 'dnspython'
    }
    
    missing_packages = []
    
    for import_name, package_name in required_packages.items():
        try:
            __import__(import_name)
        except ImportError:
            missing_packages.append(package_name)
    
    if missing_packages:
        print("\n❌ Missing required packages. Please install them using pip:")
        print("\npip install " + " ".join(missing_packages))
        print("\nOr if you're using pip3:")
        print("\npip3 install " + " ".join(missing_packages))
        exit(1)

# First check dependencies
check_dependencies()

import requests
import json
import time
import socket
import dns.resolver
import dns.reversename
from typing import Optional, Dict, List, Tuple

class CloudflareAPI:
    def __init__(self, api_token: str, zone_id: str):
        self.api_token = api_token
        self.zone_id = zone_id
        self.base_url = f"https://api.cloudflare.com/client/v4/zones/{zone_id}/dns_records"
        self.headers = {
            "Authorization": f"Bearer {api_token}",
            "Content-Type": "application/json"
        }

    def list_records(self, name: Optional[str] = None, record_type: Optional[str] = None) -> Dict:
        params = {}
        if name:
            params['name'] = name
        if record_type:
            params['type'] = record_type
            
        response = requests.get(
            self.base_url,
            headers=self.headers,
            params=params
        )
        return response.json()

    def delete_record(self, record_id: str) -> Dict:
        response = requests.delete(
            f"{self.base_url}/{record_id}",
            headers=self.headers
        )
        return response.json()

    def delete_all_matching_records(self, name: str, record_type: Optional[str] = None) -> bool:
        records = self.list_records(name, record_type)
        if records['success'] and records['result']:
            deleted = set()
            for record in records['result']:
                record_key = f"{record['type']}:{record['name']}"
                if record_key not in deleted:
                    self.delete_record(record['id'])
                    print(f"Deleted {record['type']} record for {record['name']}")
                    deleted.add(record_key)
            time.sleep(2)
            return True
        return False

    def create_record(self, record_type: str, name: str, content: str, **kwargs) -> Dict:
        self.delete_all_matching_records(name, record_type)
        
        data = {
            "type": record_type,
            "name": name,
            "content": content,
            "proxied": False
        }
        data.update(kwargs)
        
        response = requests.post(
            self.base_url,
            headers=self.headers,
            json=data
        )
        
        if response.status_code == 200:
            print(f"Successfully created {record_type} record for {name}")
        else:
            print(f"Failed to create {record_type} record for {name}")
            print(response.text)
        
        return response.json()

    def create_srv_record(self, domain: str, target: str, service: str, port: int, 
                         protocol: str = "tcp", priority: int = 0, weight: int = 1) -> Dict:
        srv_name = f"_{service}._{protocol}.{domain}"
        
        self.delete_all_matching_records(srv_name, "SRV")
        
        data = {
            "type": "SRV",
            "name": srv_name,
            "data": {
                "service": f"_{service}",
                "proto": f"_{protocol}",
                "name": domain,
                "priority": priority,
                "weight": weight,
                "port": port,
                "target": target
            }
        }
        
        response = requests.post(
            self.base_url,
            headers=self.headers,
            json=data
        )
        
        if response.status_code == 200:
            print(f"Successfully created SRV record for {srv_name}")
        else:
            print(f"Failed to create SRV record for {srv_name}")
            print(response.text)
        
        return response.json()

    def create_caa_records(self, domain: str, records: List[Dict[str, str]]) -> None:
        """Create multiple CAA records at once."""
        # First delete all existing CAA records
        self.delete_all_matching_records(domain, "CAA")
        time.sleep(1)  # Wait for deletion to propagate
        
        for record in records:
            data = {
                "type": "CAA",
                "name": domain,
                "data": {
                    "flags": 0,
                    "tag": record['tag'],
                    "value": record['value']
                },
                "proxied": False
            }
            
            response = requests.post(
                self.base_url,
                headers=self.headers,
                json=data
            )
            
            if response.status_code == 200:
                print(f"Successfully created CAA record for {domain} with {record['tag']}")
            else:
                print(f"Failed to create CAA record for {domain}")
                print(response.text)
            
            time.sleep(1)  # Wait between creating records

    def verify_record_exists(self, record_type: str, name: str) -> bool:
        records = self.list_records(name, record_type)
        return bool(records['success'] and records['result'])

    def verify_ptr_record(self, ip: str, expected_hostname: str) -> Tuple[bool, str]:
        """
        Verify PTR record for an IP address.
        Returns a tuple of (success: bool, message: str)
        """
        try:
            # Get the reverse DNS name
            reverse_name = dns.reversename.from_address(ip)
            
            # Perform reverse DNS lookup
            try:
                answers = dns.resolver.resolve(reverse_name, 'PTR')
                ptr_records = [str(answer) for answer in answers]
                
                # Remove trailing dots from hostnames for comparison
                expected_hostname = expected_hostname.rstrip('.')
                ptr_records = [record.rstrip('.') for record in ptr_records]
                
                if expected_hostname in ptr_records:
                    return True, f"PTR record correctly points to {expected_hostname}"
                else:
                    return False, f"PTR record points to {', '.join(ptr_records)} instead of {expected_hostname}"
            except dns.resolver.NXDOMAIN:
                return False, "No PTR record found"
            except Exception as e:
                return False, f"Error resolving PTR record: {str(e)}"
                
        except Exception as e:
            return False, f"Error checking PTR record: {str(e)}"

def configure_mailcow_dns(api_token: str, zone_id: str, domain: str, server_ip: str):
    cf = CloudflareAPI(api_token, zone_id)
    mail_domain = f"mail.{domain}"
    
    print("\n=== Starting DNS configuration for", domain, "===\n")
    
    # 1. Basic DNS Records
    print("1. Configuring basic DNS records...")
    basic_records = [
        ("A", mail_domain, server_ip),
        ("CNAME", f"autodiscover.{domain}", mail_domain),
        ("CNAME", f"autoconfig.{domain}", mail_domain),
        ("MX", domain, mail_domain, {"priority": 10})
    ]
    
    for record in basic_records:
        if len(record) == 4:  # With extra parameters
            cf.create_record(record[0], record[1], record[2], **record[3])
        else:
            cf.create_record(record[0], record[1], record[2])
    
    # 2. Security Records
    print("\n2. Configuring security records...")
    
    # SPF Record - More permissive configuration
    spf_record = f"v=spf1 a mx ip4:{server_ip} ~all"
    cf.create_record("TXT", domain, spf_record)
    
    # DMARC Record - Start with monitoring mode
    dmarc_record = (
        f"v=DMARC1; p=none; "
        f"rua=mailto:admin@{domain}"  # 可以修改为其他接收报告的邮箱
    )
    cf.create_record("TXT", f"_dmarc.{domain}", dmarc_record)
    
    # CAA Records
    caa_records = [
        {"tag": "issue", "value": "letsencrypt.org"},
        {"tag": "issuewild", "value": "letsencrypt.org"}
    ]
    cf.create_caa_records(domain, caa_records)
    
    # TLS Reporting
    cf.create_record("TXT", domain, f"v=TLSRPTv1;rua=mailto:tlsrpt@{domain}")
    
    # MTA-STS
    cf.create_record("TXT", f"_mta-sts.{domain}", 
                    f"v=STSv1; id={str(int(time.time()))}")
    
    # 3. Service Discovery Records
    print("\n3. Configuring service discovery records...")
    srv_configs = [
        ("submission", 587),
        ("submissions", 465),
        ("imap", 143),
        ("imaps", 993),
        ("pop3", 110),
        ("pop3s", 995),
        ("sieve", 4190),
        ("autodiscover", 443),
        ("caldavs", 443),
        ("carddavs", 443)
    ]
    
    srv_records = []
    for service, port in srv_configs:
        cf.create_srv_record(domain, mail_domain, service, port)
        srv_records.append(("SRV", f"_{service}._tcp.{domain}"))
    
    # 4. CalDAV/CardDAV Configuration
    print("\n4. Configuring CalDAV/CardDAV service paths...")
    dav_records = [
        ("TXT", f"_caldavs._tcp.{domain}", "path=/SOGo/dav/"),
        ("TXT", f"_carddavs._tcp.{domain}", "path=/SOGo/dav/")
    ]
    
    for record in dav_records:
        cf.create_record(record[0], record[1], record[2])
    
    # 5. Verify Configuration
    print("\n5. Verifying DNS configuration...")
    verification_records = (
        basic_records[:3] +  # A and CNAME records
        [("MX", domain)] +  # MX record
        [
            ("TXT", domain),  # SPF
            ("TXT", f"_dmarc.{domain}"),  # DMARC
            ("CAA", domain),  # CAA records
            ("TXT", f"_mta-sts.{domain}")  # MTA-STS
        ] +
        srv_records +  # SRV records
        dav_records  # DAV records
    )
    
    verification_failed = False
    for record in verification_records:
        record_type = record[0]
        name = record[1]
        if not cf.verify_record_exists(record_type, name):
            print(f"❌ Missing {record_type} record for {name}")
            verification_failed = True
        else:
            print(f"✓ Verified {record_type} record for {name}")

    # 6. Verify PTR record
    print("\n6. Verifying PTR record...")
    ptr_success, ptr_message = cf.verify_ptr_record(server_ip, mail_domain)
    if ptr_success:
        print(f"✓ PTR record verification: {ptr_message}")
    else:
        print(f"❌ PTR record verification failed: {ptr_message}")
        verification_failed = True
    
    print("\n=== DNS configuration completed ===")
    
    print("\nImportant next steps:")
    print("1. Configure PTR record (Reverse DNS):")
    print(f"   - Set PTR record for {server_ip} to point to {mail_domain}")
    print("   - Contact your hosting provider to set this up")
    
    print("\n2. Set up DKIM (After mailcow installation):")
    print("   - Get DKIM record from mailcow UI")
    print(f"   - Add record as: dkim._domainkey.{domain} TXT")
    
    print("\n3. Set up MTA-STS:")
    print(f"   - Create the following file: https://mail.{domain}/.well-known/mta-sts.txt")
    print("   Content should be:")
    print("   version: STSv1")
    print("   mode: enforce")
    print(f"   mx: mail.{domain}")
    print("   max_age: 604800")
    
    print("\n4. Verify your setup with:")
    print("   - https://mxtoolbox.com/SuperTool.aspx")
    print("   - https://www.mail-tester.com")
    print("   - https://dmarcian.com")
    print("   - https://ssl-tools.net/mailservers")
    
    print("\n5. Monitor your email reputation:")
    print("   - Set up Google Postmaster Tools: https://postmaster.google.com")
    print(f"   - Monitor DMARC reports at: mailauth-reports@{domain}")
    
    if verification_failed:
        print("\n⚠️  Some records are missing or incorrect. Please review the configuration.")
    else:
        print("\n✅ All DNS records verified successfully!")

if __name__ == "__main__":
    # Configuration
    API_TOKEN = "6ety2HMdB8SF7m0oolQPBU4E7EbR4G2lArHXNbZ2"
    ZONE_ID = "5db61cc39f3fe61cf378fb1bf16d066a"  # You can get this from Cloudflare dashboard
    DOMAIN = "taoziyoyo.com"
    SERVER_IP = "65.75.209.82"
    
    # Run configuration
    configure_mailcow_dns(API_TOKEN, ZONE_ID, DOMAIN, SERVER_IP)