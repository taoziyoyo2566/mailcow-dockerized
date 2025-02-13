# mailcow: dockerized - 🐮 + 🐋 = 💕

[![Translation status](https://translate.mailcow.email/widgets/mailcow-dockerized/-/translation/svg-badge.svg)](https://translate.mailcow.email/engage/mailcow-dockerized/)
[![Twitter URL](https://img.shields.io/twitter/url/https/twitter.com/mailcow_email.svg?style=social&label=Follow%20%40mailcow_email)](https://twitter.com/mailcow_email)
![Mastodon Follow](https://img.shields.io/mastodon/follow/109388212176073348?domain=https%3A%2F%2Fmailcow.social&label=Follow%20%40doncow%40mailcow.social&link=https%3A%2F%2Fmailcow.social%2F%40doncow)


## Want to support mailcow?

Please [consider a support contract with Servercow](https://www.servercow.de/mailcow?lang=en#support) to support further development. _We_ support _you_ while _you_ support _us_. :)

You can also [get a SAL](https://www.servercow.de/mailcow?lang=en#sal) which is a one-time payment with no liabilities or returning fees.

Or just spread the word: moo.

## Info, documentation and support

Please see [the official documentation](https://docs.mailcow.email/) for installation and support instructions. 🐄

🐛 **If you found a critical security issue, please mail us to [info at servercow.de](mailto:info@servercow.de).**

## Cowmunity

[mailcow community](https://community.mailcow.email)

[Telegram mailcow channel](https://telegram.me/mailcow)

[Telegram mailcow Off-Topic channel](https://t.me/mailcowOfftopic)

[Official 𝕏 (Twitter) Account](https://twitter.com/mailcow_email)

[Official Mastodon Account](https://mailcow.social/@doncow)

Telegram desktop clients are available for [multiple platforms](https://desktop.telegram.org). You can search the groups history for keywords.

## Misc

**Important**: mailcow makes use of various open-source software. Please assure you agree with their license before using mailcow.
Any part of mailcow itself is released under **GNU General Public License, Version 3**.

mailcow is a registered word mark of The Infrastructure Company GmbH, Parkstr. 42, 47877 Willich, Germany.

The project is managed and maintained by The Infrastructure Company GmbH.

Originated from @andryyy (André)

- rule:
    name: "General Spam Filter"
    description: "Intercept emails with common spam characteristics"
    score: 10.0  # 设置垃圾邮件评分，得分越高越容易被识别为垃圾邮件
    expression: |
      (
        subject =~ /(Free|Winner|Prize|Discount|Urgent|Exclusive|Claim now|Limited time offer)/i  # 检查主题是否包含垃圾邮件特征词
        || from =~ /(spam@example\.com|example@fake\.com)/i  # 检查发件人是否来自可疑域名
        || body =~ /(buy now|viagra|loan|credit card|make money|investment|weight loss)/i  # 检查邮件内容是否包含垃圾邮件关键字
        || header_exists["X-Spam-Flag"] == "YES"  # 如果邮件已有X-Spam-Flag标记
        || body =~ /(http:\/\/|https:\/\/).*\.ru/  # 检查是否包含可疑链接（例如，来自俄罗斯的链接）
        || from =~ /.*@.*\.ru/i  # 检查是否来自俄罗斯的邮箱
      )
    action: "reject"  # 如果符合规则，则拒绝该邮件
