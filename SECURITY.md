# Security Policy

## Supported Versions

We release patches for security vulnerabilities. Which versions are eligible for receiving such patches depend on the CVSS v3.0 Rating:

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

We take the security of Laravel Analytics seriously. If you believe you have found a security vulnerability, please report it to us as described below.

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to **security@epaisay.com**.

You should receive a response within 48 hours. If for some reason you do not, please follow up via email to ensure we received your original message.

Please include the following information when reporting:

- The version of Laravel Analytics you are using
- The environment (Laravel version, PHP version, database type)
- A description of the vulnerability
- Step-by-step instructions to reproduce the issue
- The impact of the issue and any potential exploit

## Preferred Languages

We prefer all communications to be in English.

## Disclosure Policy

When we receive a security bug report, we will assign it to a primary handler. This person will coordinate the fix and release process, involving the following steps:

1. Confirm the problem and determine the affected versions.
2. Audit code to find any potential similar problems.
3. Prepare fixes for all releases still under maintenance. These fixes will be released as fast as possible.

## Comments on this Policy

If you have any suggestions to improve this policy, please open an issue and we'll discuss it.

## Security Considerations

### Data Privacy
- IP addresses are stored but can be hashed for additional privacy
- User agent strings are stored for analytics purposes
- Geolocation data is obtained from public IP geolocation services
- No personally identifiable information is collected by default

### Bot Protection
- The package includes bot detection to filter out automated traffic
- Bot categorization helps identify different types of automated access

### Database Security
- All models use UUIDs instead of sequential IDs
- Comprehensive indexing for performance
- Soft deletes with audit trails

### Input Validation
- All input is validated through Laravel's validation system
- SQL injection protection through Laravel's Eloquent ORM
- XSS protection through proper output encoding

## Security Best Practices for Users

When using Laravel Analytics, we recommend:

1. **Keep the package updated** to the latest version
2. **Review configuration options** for your specific privacy requirements
3. **Implement proper access controls** for analytics data
4. **Regularly clean up old data** using the provided commands
5. **Monitor for unusual activity** in your analytics data
6. **Use HTTPS** to protect data in transit
7. **Implement rate limiting** to prevent abuse

## Security Updates

Security updates will be released as patch versions. We recommend always running the latest patch version of your major/minor version.

## Acknowledgments

We would like to thank the security researchers and users who help us keep Laravel Analytics secure by responsibly reporting vulnerabilities.

---

*This security policy is inspired by the Laravel framework's security policy.*